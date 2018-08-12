# -*- coding: utf8 -*-

import hashlib, json, requests, re
from datetime import datetime
from dns import resolver
import subprocess

def run_command(cmd):
    if type(cmd) == str:
        p = subprocess.Popen(cmd, stdout=subprocess.PIPE, shell=True)
    else:
        p = subprocess.Popen(cmd, stdout=subprocess.PIPE)

    output, err = p.communicate()
    p_status = p.wait()
    result = {"out": output, "err": err, "exit_code": p_status}
    return result

def domain_dns_resolver(url):
    host = re.match(r'(.*)://(.*?)/(.*)', url).group(2)
    try:
        rs = resolver.query(host, "CNAME").response.answer
    except Exception:
        rs = "-"

    if rs == "-":
        cdn = "none"
    else:
        if str(rs[0]).find('alikunlun.com') != -1 or str(rs[0]).find('w.kunlun') != -1:
            cdn = "aliyun"
        elif str(rs[0]).find('cdn.dnsv1.com') != -1:
            cdn = "qq"
        elif str(rs[0]).find('jcloud-cdn.com') != -1:
            cdn = "jd"
        else:
            cdn = "other"
    return cdn

headers = {"Content-Type": "application/json"}

def get_signature():
    import time
    t = time.time()
    time = datetime.fromtimestamp(t).strftime('%Y%m%d')
    str = time + 'xxxxxx'
    hl = hashlib.md5()
    hl.update(str.encode(encoding='utf-8'))
    signature = hl.hexdigest()
    return signature

def jdRefreshCdn(url_list):
    refreshDoamins = "http://opencdn.jcloud.com/api/refresh"

    parameters = {
        "username": "xxx",
        "signature": get_signature(),
        "type": "file",
        "instances": url_list
    }

    response = json.loads(requests.post(refreshDoamins, data=json.dumps(parameters), headers=headers).content)
    return response["taskid"]

def jdQueryCdn(taskid):
    queryDoamins = "http://opencdn.jcloud.com/api/refreshQuery"

    parameters = {
        "username": "xxx",
        "signature": get_signature(),
        "taskid": taskid
    }

    response = json.loads(requests.post(queryDoamins, data=json.dumps(parameters), headers=headers).content)
    return response

def qcloudRefreshCdn(type, url_list):
    if type == 'url':
        action = "RefreshCdnUrl"
        leixing = 'urls'
    else:
        leixing = 'dirs'
        action = "RefreshCdnDir"
    res = ""
    for i in url_list:
        res += "--%s" %leixing + " %s " %i
    proc = run_command(str("python huanju_qclouds_cdn_api.py %s -u username -p password \
                        %s" % (action, res)))
    if proc['out'].strip()[0:5] == 'Error':
        item_id = "error"
    else:
        item_id = proc['out'].strip()[28:-2]
    return item_id

def qcloudQueryCdn(taskid):
    time = datetime.now().strftime('%Y-%M-%d')
    proc = run_command(str("python huanju_qclouds_cdn_api.py GetCdnRefreshLog -u username -p password \
                --taskId %s --startDate %s --endDate %s" %(taskid, time, time)))
    process = re.match(r'(.*)progress\': (.*), u\'project(.*)', proc['out']).group(2)+"%"
    return process

# url = ["http://xxx.xxx.com/xxx/noc.gif","http://xxx.xxx.com/xxx/noc1.gif"]
# jdRefreshCdn(url)

# b = "task_id"
# print jdQueryCdn(b)

# print domain_dns_resolver(url="http://xxx.xxx.com/xxx/")

# url = ['http://xx.xx.com/do_not_delete/']
# id = qcloudRefreshCdn("url", url)
# print id
# res = qcloudQueryCdn("xx")
# print res
