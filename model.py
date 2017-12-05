# -*- coding: utf8 -*-

from flask_sqlalchemy import SQLAlchemy
from flask import Flask
from datetime import datetime

class Config(object):
    SQLALCHEMY_DATABASE_URI = "mysql+pymysql://root:root@localhost:3306/test"

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

class cdn_info(db.Model):
    __tablename__ = 'cdn_info'
    id = db.Column(db.Integer,primary_key=True,autoincrement=True)
    RefreshTaskId = db.Column(db.Integer,nullable=False)
    url_dir = db.Column(db.String(255),nullable=False)
    creat_time = db.Column(db.DateTime,default=datetime.now)
    Process = db.Column(db.String(10),nullable=False)
    user = db.Column(db.String(50),nullable=False)

    def __init__(self,RefreshTaskId,url_dir,Process,user):
        self.RefreshTaskId = RefreshTaskId
        self.url_dir = url_dir
        self.Process = Process
        self.user = user