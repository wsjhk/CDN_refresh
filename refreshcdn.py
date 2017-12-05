import sys
import urllib, urllib2
import base64
import hmac
from hashlib import sha1
import time
import uuid
import json
import logging

logging.basicConfig(level=logging.DEBUG, format='%(asctime)s %(filename)s[line:%(lineno)d] %(levelname)s: %(message)s',
                    datefmt='%a, %d %b %Y %H:%M:%S',
                    filename='E:\copy.log',
                    filemode='w')

##global parameter should be defined
SRC_REGION_ID = "cn-shanghai"
DES_REGION_ID = "cn-shanghai"
ACCESS_KEY_ID = 'LTAIVvGl9tMybPNB'
ACCESS_KEY_SECRET = '17JEnZmMTM5H4FpzhEpu4Yttd51Xa2'
DISK_COLUMN_BASE = 19
DISK_PARA_NUM = 6
CREATE_INSTANCE_BASE = 50


class AliyumCdnApi:
    def __init__(self):
        self.ecs_server_address = "http://cdn.aliyuncs.com"
        self.access_key_id = ACCESS_KEY_ID
        self.access_key_secret = ACCESS_KEY_SECRET

    def percent_encode(self, str):
        res = urllib.quote(str.decode(sys.stdin.encoding).encode('utf8'), '')
        res = res.replace('+', '%20')
        res = res.replace('*', '%2A')
        res = res.replace('%7E', '~')
        return res

    def compute_signature(self, parameters, access_key_secret):
        sortedParameters = sorted(parameters.items(), key=lambda parameters: parameters[0])
        canonicalizedQueryString = ''
        for (k, v) in sortedParameters:
            canonicalizedQueryString += '&' + self.percent_encode(k) + '=' + self.percent_encode(v)
        stringToSign = 'GET&%2F&' + self.percent_encode(canonicalizedQueryString[1:])
        h = hmac.new(access_key_secret + "&", stringToSign, sha1)
        signature = base64.encodestring(h.digest()).strip()
        return signature

    def compose_url(self, user_params):
        timestamp = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
        parameters = { \
            'Format': 'JSON', \
            'Version': '2014-11-11', \
            'AccessKeyId': self.access_key_id, \
            'SignatureVersion': '1.0', \
            'SignatureMethod': 'HMAC-SHA1', \
            'SignatureNonce': str(uuid.uuid1()), \
            'Timestamp': timestamp, \
            # "ClientToken"            :"yicheng"
        }
        for key in user_params.keys():
            parameters[key] = user_params[key]
        signature = self.compute_signature(parameters, self.access_key_secret)
        parameters['Signature'] = signature
        url = self.ecs_server_address + "/?" + urllib.urlencode(parameters)
        return url

    def make_request(self, user_params, quiet=False):
        url = self.compose_url(user_params)
        #        print url
        try:
            req = urllib2.Request(url)
            res_data = urllib2.urlopen(req)
            res = res_data.read()
            return res
        except Exception, e:
            return e.read()


def PreloadObjCache(strDomainName, strURLs):
    params = {'Action': "PreloadObjectCaches", "DomainName": strDomainName, "ObjectPath": strURLs}
    f = AliyumCdnApi()
    response = ""
    response = json.loads(f.make_request(params))
    print "Response is %s:" % str(response)
    return response


def PurgeObjCache(strDomainName, strURLs, strType):
    params = {'Action': "PurgeObjectCaches", "DomainName": strDomainName, "ObjectPath": strURLs, "ObjectType": strType}
    f = AliyumCdnApi()
    response = ""
    response = json.loads(f.make_request(params))
    print "Response is %s:" % str(response)
    return response

def SelectObjCache(strDomainName,TaskId,strType):
    params = {'Action': "DescribeRefreshTasks", "DomainName": strDomainName, "TaskId": TaskId, "ObjectType": strType}
    f = AliyumCdnApi()
    response = ""
    response = json.loads(f.make_request(params))
    print "Response is %s:" % str(response)
    return response