# -*- coding: utf8 -*-

from flask_sqlalchemy import SQLAlchemy
from flask import Flask
from datetime import datetime

class Config(object):
    SQLALCHEMY_DATABASE_URI = "mysql+pymysql://root:root@localhost:3306/test"
    SQLALCHEMY_POOL_SIZE = 100

'''
1.配置SQLAlchemy的连接池SQLALCHEMY_POOL_SIZE参数
2.重要：sql操作用try块处理，每次操作都要close数据库链接。解决数据库以下报错问题。
错误1：
TimeoutError: QueuePool limit of size 5 overflow 10 reached, connection timed out, timeout 30
错误2：
[ERROR] (OperationalError) (2006, 'MySQL server has gone away')
'''

app = Flask(__name__)
app.config.from_object(Config)
db = SQLAlchemy(app)

class auth_user(db.Model):
    __tablename__ = 'auth_user'
    id = db.Column(db.Integer, primary_key=True ,autoincrement=True)
    username = db.Column(db.String(50),nullable=False)
    email = db.Column(db.String(50),nullable=False)
    password = db.Column(db.String(100),nullable=False)

    def __init__(self,username,email,password):
        self.username = username
        self.email = email
        self.password = password

class cdn_info_new(db.Model):
    __tablename__ = 'cdn_info_new'
    id = db.Column(db.Integer,primary_key=True,autoincrement=True)
    RefreshTaskId = db.Column(db.Integer,nullable=False)
    url_dir = db.Column(db.String(255),nullable=False)
    create_time = db.Column(db.DateTime,default=datetime.now,nullable=False)
    Process = db.Column(db.String(10),nullable=False)
    user = db.Column(db.String(50),nullable=False)
    status = db.Column(db.Enum("1","2","3","4"), default="1")   #表示刷新的状态，1表示等待刷新，2表示刷新中，3表示已刷新，4表示刷新失败

    def __init__(self,RefreshTaskId,url_dir,Process,user,status):
        self.RefreshTaskId = RefreshTaskId
        self.url_dir = url_dir
        self.Process = Process
        self.user = user
        self.status = status