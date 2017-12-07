# -*- coding: utf8 -*-

import json,urllib
import requests

aliyun_uri = "你的域名"
aliyun_appid = "你的ID"
aliyun_appsecret = "你的key"

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

def RefreshCdn(url, type, token):
    # 阿里云刷新和预取函数，刷新支持文件+目录，预取只支持文件，不支持目录预取
    sy = huanju_cdn_api()
    sy.set_endPoint("/api/RefreshCache")
    #刷新目录的话，后缀需要是/结尾
    #type:flush/prefetch  刷新/预取
    params = {
            "url":url,
            "type":type,
            "access_token":token
        }
    sy.set_body(params)
    sy.set_method("POST")
    result = sy.do_request()
    cdn_value_list = []
    print result
    if result and result['status'] == 1 :
        refresh_cdn_result = result["result_desc"]
        item_id = refresh_cdn_result.get("item_id")
        return  item_id
    else:
        return False

def QueryCdn(item_id, token):
    # 根据刷新后的item_id进行查询刷新结果
    sy = huanju_cdn_api()
    sy.set_endPoint("/api/GetTaskStatus")
    params = {
            "item_id":item_id,
            "access_token":token
        }
    sy.set_body(params)
    sy.set_method("POST")
    result = sy.do_request()
    refresh_cdn_status = {}
    if result and result['status'] == 1 :
        refresh_cdn_dict = result["result_desc"]
        return refresh_cdn_dict
    else:
        return False

# if __name__ == '__main__':
#     token = GetApiToken()
#     url = ["http://www.abc.com/test.json","dd.json"]
#     item_id = RefreshCdn(url,"flush",token)
#     print QueryCdn("1709932202",token)
#     print QueryCdn("1709932211,1710642282", token)
