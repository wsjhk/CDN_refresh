# -*- coding: utf8 -*-

import json
import requests
import os.path,urllib,time

aliyun_uri = "http://yy.cdnpe.com"
aliyun_appid = "xxx"
aliyun_appsecret = "xxxxxx"

class huanju_cdn_api():
    def __init__(self):
        self.accessId = aliyun_appid
        self.secretKey = aliyun_appsecret
        self.uri = aliyun_uri
        self.endPoint = ''
        self.method = ''

    def set_endPoint(self,value):
        self.endPoint = value
        return self.endPoint

    def set_method(self, value):
        self.method = value.upper()
        return self.method

    def set_body(self,value):
        self.body = value
        return  self.body

    def do_request(self):
        if self.method == 'GET' :
            url = self.uri + self.endPoint + '?'+ urllib.urlencode(self.body)
            try:
                req = requests.get(url)
                result = json.loads(req.text)
            except Exception,e:
                return False
            else :
                return result
        if self.method == 'POST' :
            try:
                req = requests.post(self.uri + self.endPoint ,data=json.dumps(self.body) )
                result = json.loads(req.text)
            except Exception,e:
                return False
            else :
                return result

def GetApiToken():
    # 案例1：获取api接口的请求token
    sy = huanju_cdn_api()
    sy.set_endPoint("/api/AccessToken")
    params = {
                "appid": aliyun_appid,
                "appsecret": aliyun_appsecret
        }
    sy.set_body(params)
    sy.set_method("POST")
    result = sy.do_request()
    if result and result['status'] == 1 :
        token = result["result_desc"]["access_token"]
        return token
    else:
        return False

def get_log(starttime, endtime, domain, token):
    sy = huanju_cdn_api()
    sy.set_endPoint("/api/GetDomainLogs")
    params = {
        "start_time": starttime,
        "end_time": endtime,
        "domain": domain,
        "type":"edge",
        "access_token": token
    }
    sy.set_body(params)
    sy.set_method("POST")
    result = sy.do_request()
    return result

def down(_save_path, _url):
    try:
        urllib.urlretrieve(_url, _save_path)
    except:
        print '\nError when retrieving the URL:', _save_path


if __name__ == '__main__':
    token = GetApiToken()
    savedir = 'F:\cdn_logs'

    starttime = "2018-11-21 11:00:00"
    endtime = "2018-11-21 15:00:01"
    domain = "xx.xx.com"
    #将格式化时间转换成时间戳
    S_timeArray = time.strptime(starttime, "%Y-%m-%d %H:%M:%S")
    S_timestamp = time.mktime(S_timeArray)
    E_timeArray = time.strptime(endtime, "%Y-%m-%d %H:%M:%S")
    E_timestamp = time.mktime(E_timeArray)
    _starttime = S_timestamp
    _endtime = E_timestamp
    _tmptime = _starttime + 300
    # 使用时间戳的方式来转化成格式化的时间循环每五分钟取一次日志包,五分钟对应的时间戳就是300秒
    while _starttime < _endtime:
        # 将时间戳转换成格式化时间
        S_time_local = time.localtime(_starttime)
        S_dt = time.strftime("%Y-%m-%d %H:%M:%S", S_time_local)
        E_time_local = time.localtime(_tmptime)
        E_dt = time.strftime("%Y-%m-%d %H:%M:%S", E_time_local)
        print S_dt,E_dt

        res = get_log(S_dt, E_dt, domain, token)
        print res
        url = "http://" + res['result_desc'][S_dt]['data_list'][domain]['isp_cnc']['url']
        print url
        dest_dir = os.path.join(savedir, S_dt.replace(' ','_').replace(':','_').replace('-','_')+".gz")
        down(dest_dir, url)
        _starttime = _tmptime
        _tmptime += 300
