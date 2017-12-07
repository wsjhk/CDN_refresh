# -*- coding: utf8 -*-

from flask import Flask,render_template,request,redirect,jsonify,session,url_for,make_response
import subprocess,os
from model import auth_user,cdn_info_new,db
from functools import wraps

app = Flask(__name__)
app.config['SECRET_KEY'] = os.urandom(24)
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = True

#用于验证是否登录的装饰器
def login_required(func):
    @wraps(func)
    def wrappper(*args,**kwargs):
        if session.get('user_username'):
            return func(*args,**kwargs)
        else:
            return redirect(url_for('login'))
    return wrappper

#刷新cdn时定义的用于保存接收页面传递的cdn信息和执行刷新操作的类
class refresh(object):
    def __init__(self,domain,dir):
        self.domains = list(set(domain))        #将接收的地址进行去重处理
        self.dirs = list(set(dir))

    def refreshop(self):
        url_list,dir_list = [],[]
        for domain in self.domains:
            if domain:          #判断是否是空行的情况，是的话什么也不做
                url_list.append(domain)
            else:
                continue
        for dir in self.dirs:
            if dir:
                url_list.append(dir)
                dir_list.append(dir)
            else:
                continue
        if len(url_list) <= 1 and url_list[0] == '':
            data = {'status': "kong"}
        else:
            for i in url_list:
                try:
                    info = cdn_info_new(0,i,"0%",session.get('user_username'),"1")
                    db.session.add(info)
                    db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
            data = {'status': "OK"}
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
        try:
            user = auth_user.query.filter_by(username = username,password = password).first()
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
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
    try:
        user_info = auth_user.query.filter_by(username = username).first()
        if user_info is None and username.startswith("dw_"):
            add_user = auth_user(username,yy_email,password)
            db.session.add(add_user)
            db.session.commit()
    except Exception:
        print "DB Error."
        return None
    finally:
        db.session.close()
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
        return render_template('dashboard.html',user = session['user_username'])

# 主功能查询视图函数,处理查询用户的所有信息
@app.route('/select2')
@app.route('/select1', methods=['GET', 'POST'])
@app.route('/select1/<int:page>', methods=['GET', 'POST'])
@login_required
def select1(page=1):
    if request.method == 'POST':
        danhao = request.form.get('danhao')
        try:
            if danhao and cdn_info_new.query.filter_by(url_dir=danhao).filter_by(user=session['user_username']).all():
                pagination = cdn_info_new.query.filter_by(url_dir=danhao).filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
                data = pagination.items
                return render_template('select2.html',data = data,user = session['user_username'],pagination=pagination)
            else:
                return render_template('select2.html', data="none",user = session['user_username'],pagination="none")
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
    else:
        if cdn_info_new.query.filter_by(user=session['user_username']).all():
            try:
                pagination = cdn_info_new.query.filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
                data = pagination.items
            except Exception:
                print "DB Error."
                return None
            finally:
                db.session.close()
            return render_template('select1.html',data = data,user = session['user_username'],pagination=pagination)
        else:
            return render_template('select1.html', data="none", user=session['user_username'], pagination="none")

# 主功能查询视图函数,处理用户查询某个url的信息
@app.route('/select2/<int:page>/')
@login_required
def select2(page=1):
    danhao = request.args.get('danhao')
    try:
        if danhao and cdn_info_new.query.filter_by(url_dir=danhao).filter_by(user=session['user_username']).all():
            pagination = cdn_info_new.query.filter_by(url_dir=danhao).filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
            data = pagination.items
            return render_template('select2.html',data = data,user = session['user_username'],pagination=pagination)
        else:
            return render_template('select2.html', data="none",user = session['user_username'],pagination="none")
    except Exception:
        print "DB Error."
        return None
    finally:
        db.session.close()

#登出视图函数
@app.route("/logout")
def logout():
    session.pop('user_username')
    return redirect(url_for('login'))


if __name__ == '__main__':
    app.run(host="0.0.0.0", port=5000)
