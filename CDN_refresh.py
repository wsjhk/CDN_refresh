# -*- coding: utf8 -*-

from flask import Flask,render_template,request,redirect,jsonify,session,url_for,g,make_response
# from flask_httpauth import HTTPBasicAuth
import refreshcdn
import subprocess,os
from model import auth_user,cdn_info,db
from functools import wraps

# auth = HTTPBasicAuth()
app = Flask(__name__)
app.config['SECRET_KEY'] = os.urandom(24)
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = True

# db.create_all()

#用于验证是否登录的装饰器
def login_required(func):
    @wraps(func)
    def wrappper(*args,**kwargs):
        if session.get('user_username'):
            return func(*args,**kwargs)
        else:
            return redirect(url_for('login'))
    return wrappper

#before_request钩子函数，每个请求前执行的函数，设置全局变量g的值
@app.before_request
def my_before_request():
    user_username = session.get('user_username')
    if user_username:
        user = auth_user.query.filter(auth_user.username == user_username)
        if user:
            g.user = user

#上下文处理钩子函数
@app.context_processor
def my_context_processor():
    user_username = session.get('user_username')
    if user_username:
        user = g.user
        if user:
            return {'user': user}
    return {}

#刷新cdn时定义的用于保存接收页面传递的cdn信息和执行刷新操作的类
class refresh(object):
    def __init__(self,domain,dir):
        self.domains = list(set(domain))        #将接收的地址进行去重处理
        self.dirs = list(set(dir))

    def refreshop(self):
        a,b = {},{}
        print self.domains,self.dirs
        if len(self.domains) <= 1 and self.domains[0] == '':
            a['RefreshTaskId'] = "000"
        else:
            for domain in self.domains:
                if domain:          #判断是否是空行的情况，是的话什么也不做
                    a = refreshcdn.PurgeObjCache("cqms.cdn.gop.yy.com", domain, "File")
                else:
                    continue

        for dir in self.dirs:
            if dir:
                b = refreshcdn.PurgeObjCache("cqms.cdn.gop.yy.com", dir, "File")
            else:
                continue
        if a['RefreshTaskId'] == "000":
            res = refreshcdn.SelectObjCache("cqms.cdn.gop.yy.com", b['RefreshTaskId'], "File")['Tasks']['CDNTask']
        else:
            res = refreshcdn.SelectObjCache("cqms.cdn.gop.yy.com", a['RefreshTaskId'], "File")['Tasks']['CDNTask']
        for i in range(0, len(res)):
            info = cdn_info(res[i]['TaskId'], res[i]['ObjectPath'], res[i]['Process'],session.get('user_username'))
            db.session.add(info)
            db.session.commit()
        data = {'status': "OK",'task_id':res[len(res)-1]['TaskId']}
        return data

#封装的执行系统命令的函数
def run_command(cmd):
    if type(cmd) == str:
        p = subprocess.Popen(cmd, stdout=subprocess.PIPE, shell=True)
    else:
        p = subprocess.Popen(cmd, stdout=subprocess.PIPE)

    output, err = p.communicate()
    p_status = p.wait()
    result = {"out": output, "err": err, "exit_code": p_status}
    return result

#登录视图函数
@app.route('/', methods=['GET','POST'])
@app.route('/login', methods=['GET','POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        user = auth_user.query.filter_by(username = username,password = password).first()
        if user:
            session['user_username'] = user.username
            return redirect(url_for('dashboard'))
        else:
            return render_template('login.html')
    else:
        return render_template('login.html')

#YY登录进行验证，成功后，回调 yy_oauth函数进行创用户
@app.route('/yy_cdnlogin', methods=['GET','POST'])
def yy_cdnlogin():
    proc = run_command('php E:\myfiles\projects\CDN_refresh\yy_oauth\login.php')
    return make_response(redirect(proc['out']))

#YY登录回调的url,首次登录会创建用户
@app.route('/yy_cdnoauth', methods=['GET','POST'])
def yy_cdnoauth():
    oauth_token = request.args.get('oauth_token')
    oauth_verifier = request.args.get('oauth_verifier')
    proc = run_command("php E:\myfiles\projects\CDN_refresh\yy_oauth\login.php do=yy_oauth oauth_token="+oauth_token+" oauth_verifier="+oauth_verifier)
    script_response = proc['out']
    yy_user = script_response.split('-')
    username = yy_user[0]
    yy_email = username[3:]+"@yy.com"
    password = yy_user[0]+"@yy.com"
    user_info = auth_user.query.filter_by(username=username).first()
    if user_info is None and username.startswith("dw_"):
        add_user = auth_user(username,yy_email,password)
        db.session.add(add_user)
        db.session.commit()
    session['user_username'] = username
    return redirect(url_for('dashboard'))

#YY登录拒绝回调的url
@app.route('/yy_denyCallback', methods=['GET','POST'])
def yy_denyCallback():
    pass

#主功能刷新视图函数
@app.route('/dashboard', methods=['GET','POST'])
@login_required
def dashboard():
    if request.method == 'POST':
        domains = request.form.get('domain').split('\n')
        dirs = request.form.get('dir').split('\n')
        curr = refresh(domains,dirs)
        data = curr.refreshop()
        return jsonify(data)
    else:
        return render_template('dashboard.html')

# 主功能查询视图函数
@app.route('/select', methods=['GET', 'POST'])
@login_required
def select():
    if request.method == 'POST':
        danhao = request.form.get('danhao')
        if danhao and cdn_info.query.filter_by(RefreshTaskId=danhao).all():
            res = refreshcdn.SelectObjCache("cqms.cdn.gop.yy.com", danhao, "File")['Tasks']['CDNTask']
            for i in range(0, len(res)):
                info = cdn_info.query.filter_by(RefreshTaskId=danhao).filter_by(url_dir=res[i]['ObjectPath']).first()
                info.Process = res[i]['Process']
                db.session.commit()
            data = cdn_info.query.filter_by(RefreshTaskId=danhao).all()
            return render_template('select.html',data = data)
        else:
            return render_template('select.html', data="none")
    else:
        return render_template('select.html',data = "none")

#登出视图函数
@app.route("/logout")
def logout():
    session.pop('user_username')
    return redirect(url_for('login'))

if __name__ == '__main__':
    app.run(host="0.0.0.0", port=5000)
