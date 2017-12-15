# -*- coding: utf8 -*-

import huanju_aliyun_cdn_api
from model import cdn_info_new,db
import time,threading
from datetime import datetime

# db.create_all()

token = huanju_aliyun_cdn_api.GetApiToken()

def refresh():
    id_list,url_list = [],[]
    try:
        info = cdn_info_new.query.filter_by(status="1").all()
    except Exception:
        print "DB Error."
        return None
    finally:
        db.session.close()
    if info:
        for i in info:
            id_list.append(i.id)
            url_list.append(i.url_dir)
        item_id = huanju_aliyun_cdn_api.RefreshCdn(url_list, "flush", token)

        if "," in item_id:
            url_id = item_id.split(",")[0]
            dir_id = item_id.split(",")[1]
        else:
            url_id,dir_id = item_id,item_id

        for j in range(0, len(id_list)):
            try:
                if url_list[j][len(url_list[j]) - 1] == "/":
                    update = cdn_info_new.query.filter_by(id=id_list[j]).first()
                    update.status = "2"
                    update.RefreshTaskId = dir_id
                else:
                    update = cdn_info_new.query.filter_by(id=id_list[j]).first()
                    update.status = "2"
                    update.RefreshTaskId = url_id
                db.session.commit()
            except Exception:
                print "DB Error."
                return None
            finally:
                db.session.close()
    else:
        pass

def select():
    id_list,item_id_list = [],[]
    try:
        info1 = cdn_info_new.query.filter_by(status="2").all()
    except Exception:
        print "DB Error."
        return None
    finally:
        db.session.close()
    if info1:
        for i in info1:
            # id_list.append(i.id)
            item_id_list.append(i.RefreshTaskId)
        item_id_list = list(set(item_id_list))
        for j in item_id_list:
            res = huanju_aliyun_cdn_api.QueryCdn(str(j), token)
            for k,v in res.items():
                try:
                    update = cdn_info_new.query.filter_by(url_dir=k).filter_by(RefreshTaskId=j).all()
                    for one in update:
                        one.Process = v['rate']
                        if v['rate'] == "100%":
                            one.status = "3"
                        else:
                            one.status = "2"
                        db.session.commit()
                except Exception:
                    print "DB Error."
                    return None
                finally:
                    db.session.close()
    else:
        pass


def shibai():
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
    print("[" + datetime.now() + "]: task start.")
    threads = []
    t1 = threading.Thread(target=refresh)
    t2 = threading.Thread(target=select)
    t3 = threading.Thread(target=shibai)
    threads.append(t1)
    threads.append(t2)
    threads.append(t3)
    for t in threads:
        t.setDaemon(True)
        t.start()
        time.sleep(15)
    print("[" + datetime.now() + "]: task end.")
