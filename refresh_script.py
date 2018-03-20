import huanju_aliyun_cdn_api
from model import cdn_info_new,db
import time
from multiprocessing import Process
from datetime import datetime

# db.create_all()


#将数据库中记录状态为1的等待刷新的资源提交到阿里云上刷新，提交之后修改状态为2刷新中
def refresh_url(type):
    while True:
        time.sleep(10)
        # 获取阿里云接口认证的token
        token = huanju_aliyun_cdn_api.GetApiToken()
        try:
            info = cdn_info_new.query.filter_by(status="1").filter_by(type=type).all()
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
        if info:
            for i in info:
                url = [i.resources]
                item_id = huanju_aliyun_cdn_api.RefreshCdn(url, "flush", token)
                try:
                    update = cdn_info_new.query.filter_by(id=i.id).first()
                    update.status = "2"
                    update.RefreshTaskId = item_id
                    db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
        else:
            pass

def refresh_dir(type):
    while True:
        time.sleep(10)
        # 获取阿里云接口认证的token
        token = huanju_aliyun_cdn_api.GetApiToken()
        try:
            info0 = cdn_info_new.query.filter_by(status="1").filter_by(type=type).all()
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
        if info0:
            for i in info0:
                url = [i.resources]
                item_id = huanju_aliyun_cdn_api.RefreshCdn(url, "flush", token)
                try:
                    update = cdn_info_new.query.filter_by(id=i.id).first()
                    update.status = "2"
                    update.RefreshTaskId = item_id
                    db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
        else:
            pass

#将数据库中状态为2的刷新中的资源的item_id取出用于查询在阿里云上的刷新进度，查询之后更新进度的，如果进度100%则更新状态为3已刷新。
def select():
    while True:
        time.sleep(10)
        # 获取阿里云接口认证的token
        token = huanju_aliyun_cdn_api.GetApiToken()
        try:
            info1 = cdn_info_new.query.filter(cdn_info_new.RefreshTaskId != "0").filter_by(status="2").all()
            print info1
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()
        if info1:
            for i in info1:
                item_id = str(i.RefreshTaskId)
                res = huanju_aliyun_cdn_api.QueryCdn(item_id, token)
                try:
                    update = cdn_info_new.query.filter_by(id=i.id).first()
                    update.Process = res[i.resources]['rate']
                    if res[i.resources]['rate'] == "100%":
                        update.status = "3"
                    else:
                        update.status = "2"
                    db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
        else:
            pass

#将数据库中状态为2的刷新中的资源，核对提交的时间和现在的时间差，如果超过半小时，则认为刷新失败，修改状态为4刷新失败。
def shibai():
    while True:
        time.sleep(10)
        try:
            info2 = cdn_info_new.query.filter_by(status="2").all()
            if info2:
                for one in info2:
                    t1 = datetime.strptime(datetime.strftime(datetime.now(),"%Y-%m-%d %H:%M:%S"),"%Y-%m-%d %H:%M:%S")
                    t2 = datetime.strptime(datetime.strftime(one.create_time,"%Y-%m-%d %H:%M:%S"),"%Y-%m-%d %H:%M:%S")
                    if (t1 - t2).seconds/60 > 31:
                        one.status = "4"
                        db.session.commit()
                    else:
                        continue
            else:
                pass
        except Exception:
            print "DB Error."
            return None
        finally:
            db.session.close()

if __name__ == '__main__':
    ps = []
    p1 = Process(target=refresh_url, args=("url",))
    p2 = Process(target=refresh_dir, args=("dir",))
    p3 = Process(target=select)
    p4 = Process(target=shibai)
    ps.append(p1)
    ps.append(p2)
    ps.append(p3)
    ps.append(p4)
    for p in ps:
        p.start()
