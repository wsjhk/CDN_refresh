# -*- coding: utf8 -*-

from flask import Flask,render_template,request,redirect,jsonify,session,url_for,make_response,abort
import subprocess,os
from model import auth_user,cdn_info_new,db
from functools import wraps
import huanju_aliyun_cdn_api, haunju_jd_cdn_api
from flask.ext.httpauth import HTTPBasicAuth
auth = HTTPBasicAuth()

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
    def __init__(self, domain, dir):
        self.domains = list(set(domain))        #将接收的地址进行去重处理
        self.dirs = list(set(dir))

    def commit(self, resources, type):
        resource_list = []
        for resource in resources:
            if resource:
                resource_list.append(resource)
            else:
                continue
        if len(resource_list) <= 1 and resource_list[0] == 'none':
            status = {'status': "kong"}
        else:
            for i in resource_list:
                try:
                    info = cdn_info_new("0",i,type,"0%",session.get('user_username'),"1")
                    db.session.add(info)
                    db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
            status = {'status': "OK"}
        return status

    def refreshop(self):
        url = self.commit(self.domains, "url")
        dir = self.commit(self.dirs, "dir")
        if url['status'] == "kong" and dir['status'] == "kong":
            data = {'status': "kong"}
        else:
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
@app.route('/', methods=['GET', 'POST'])
@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        try:
            user = auth_user.query.filter_by(username=username, password=password).first()
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
@app.route('/yy_cdnlogin', methods=['GET', 'POST'])
def yy_cdnlogin():
    proc = run_command('php .\yy_oauth\login.php')
    return make_response(redirect(proc['out']))

#YY登录回调的url,首次登录会创建用户
@app.route('/yy_cdnoauth', methods=['GET', 'POST'])
def yy_cdnoauth():
    oauth_token = request.args.get('oauth_token')
    oauth_verifier = request.args.get('oauth_verifier')
    proc = run_command(str("php .\yy_oauth\login.php do=yy_oauth oauth_token="+oauth_token+" oauth_verifier="+oauth_verifier))
    script_response = proc['out']
    yy_user = script_response.split('-')
    username = yy_user[0]
    yy_email = username[3:]+"@yy.com"
    password = yy_user[0]+"@yy.com"
    try:
        user_info = auth_user.query.filter_by(username=username).first()
        if user_info is None:
            add_user = auth_user(username, yy_email, password)
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
@app.route('/yy_denyCallback', methods=['GET', 'POST'])
def yy_denyCallback():
    pass

#主功能刷新视图函数
@app.route('/dashboard', methods=['GET', 'POST'])
@login_required
def dashboard():
    if request.method == 'POST':
        domains = request.form.get('domain').strip().split('\n')
        dirs = request.form.get('dir').strip().split('\n')
        if dirs[0] == "":
            dirs[0] = "none"
        if domains[0] == "":
            domains[0] = "none"
        curr = refresh(domains, dirs)
        data = curr.refreshop()
        return jsonify(data)
    else:
        return render_template('dashboard.html', user=session['user_username'])

# 主功能查询视图函数,处理查询用户的所有信息
@app.route('/select2')
@app.route('/select1', methods=['GET', 'POST'])
@app.route('/select1/<int:page>', methods=['GET', 'POST'])
@login_required
def select1(page=1):
    if request.method == 'POST':
        danhao = request.form.get('danhao')
        try:
            if danhao and cdn_info_new.query.filter_by(resources=danhao).filter_by(user=session['user_username']).all():
                pagination = cdn_info_new.query.filter_by(resources=danhao).filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
                data = pagination.items
                return render_template('select2.html', data=data, user = session['user_username'], pagination=pagination)
            else:
                return render_template('select2.html', data="none", user = session['user_username'], pagination="none")
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
    else:
        try:
            if cdn_info_new.query.filter_by(user=session['user_username']).all():
                pagination = cdn_info_new.query.filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
                data = pagination.items
                return render_template('select1.html', data=data, user=session['user_username'], pagination=pagination)
            else:
                return render_template('select1.html', data="none", user=session['user_username'], pagination="none")
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()


# 主功能查询视图函数,处理用户查询某个url的信息
@app.route('/select2/<int:page>/')
@login_required
def select2(page=1):
    danhao = request.args.get('danhao')
    try:
        if danhao and cdn_info_new.query.filter_by(resources=danhao).filter_by(user=session['user_username']).all():
            pagination = cdn_info_new.query.filter_by(resources=danhao).filter_by(user=session['user_username']).order_by(cdn_info_new.create_time.desc()).paginate(page, per_page=15, error_out=False)
            data = pagination.items
            return render_template('select2.html', data=data, user=session['user_username'], pagination=pagination)
        else:
            return render_template('select2.html', data="none", user=session['user_username'], pagination="none")
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

@auth.get_password
def get_password(username):
    if username == 'huanjucdn':
        return 'huanjucdn@yy'
    return None

@auth.error_handler
def unauthorized():
    return make_response(jsonify({'error': 'Unauthorized access'}), 403)

@app.route('/cdnflush/api/v1.0/resources/<string:item_id>', methods=['GET'])
@auth.login_required
def get_resource(item_id):
    try:
        a = item_id.split(',')[1]
        token = huanju_aliyun_cdn_api.GetApiToken()
        result = huanju_aliyun_cdn_api.QueryCdnAPI(item_id, token)
        return jsonify({'resource': result})
    except Exception:
        if len(item_id) < 16:
            token = huanju_aliyun_cdn_api.GetApiToken()
            result = huanju_aliyun_cdn_api.QueryCdnAPI(item_id, token)
            return jsonify({'resource': result})
        elif len(item_id) > 16 and len(item_id) < 25:
            result = huanju_jd_cdn_api.qcloudQueryCdn(item_id)
            if result == "100%":
                return jsonify({'resource': item_id, 'status': result})
            else:
                return jsonify({'resource': item_id, 'status': result})
        else:
            result = huanju_jd_cdn_api.jdQueryCdn(item_id)
            if result["data"]["msg"] == u"已完成":
                return jsonify({'resource': result,'status': "100%"})
            else:
                return jsonify({'resource': result,'status': "0%"})

@app.route('/cdnflush/api/v1.0/resources', methods=['POST'])
@auth.login_required
def flush_resource():
    if not request.json or not 'url' in request.json:
        abort(400)
    url = request.json['url']
    cdn_p = huanju_jd_cdn_api.domain_dns_resolver(url[0])
    if  cdn_p == "aliyun":
        token = huanju_aliyun_cdn_api.GetApiToken()
        item_id = huanju_aliyun_cdn_api.RefreshCdnAPI(url, "flush", token)
        return jsonify({'resource': item_id}), 201
    elif cdn_p == "jd":
        item_id = huanju_jd_cdn_api.jdRefreshCdn("file", url)
        return jsonify({'resource': "Refresh committed.", 'item_id': item_id}), 201
    elif cdn_p == "qq":
        item_id = huanju_jd_cdn_api.qcloudRefreshCdn("url", url)
        return jsonify({'resource': "Refresh committed.", 'item_id': item_id}), 201
    elif cdn_p == "other":
        return jsonify({'resource': "Only to refresh CDN for Aliyun,JDcloud and Tencent."}), 201
    else:
        return jsonify({'resource': "Your domain is not support CDN,Please to check!"}), 201

if __name__ == '__main__':
    # from werkzeug.contrib.fixers import ProxyFix
    # app.wsgi_app = ProxyFix(app.wsgi_app)
    # gunicorn -w 4 -b 127.0.0.1:5000 CDN_Refresh:app       //use gunicorn to run the app.
    app.run(host="0.0.0.0", port=5000)
    
