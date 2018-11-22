# -*- coding:utf-8 -*-

import os, json, xlwt
from xlrd import open_workbook
from xlutils.copy import copy
from datetime import datetime

num = 1
size = 50
domains = []


def init_sql_perform_history(filename):
    timestamp = datetime.now().strftime("%Y%m%d")
    wb = xlwt.Workbook()
    wb.add_sheet(timestamp)
    wb.save(filename)


def write_to_xls(filename, row, col, value):
    if os.path.isfile(filename):
        rb = open_workbook(filename)
        wb = copy(rb)
        sheet = wb.get_sheet(0)
        sheet.write(row, col, value)
        wb.save(filename)


while (num <= (669 / 50) + 1):
    a = os.popen(
        """python aliyun_CDN.py Action=DescribeUserDomains PageNumber=%s PageSize=%s""" % (num, size)).readlines()
    c = json.loads(a[0])
    num = num + 1
    i = 0
    b = c["Domains"]["PageData"]
    while (i < len(b)):
        domains.append(b[i]["DomainName"])
        i = i + 1

filename = "/home/huangjie1/CDN_count.xls"
j = 0
init_sql_perform_history(filename)
while (j < len(domains)):
    starttime3 = '2018-06-01T00:00:00:00Z'
    endtime3 = '2018-06-30T23:59:59:00Z'
    starttime4 = '2018-07-01T00:00:00:00Z'
    endtime4 = '2018-07-31T23:59:59:00Z'
    starttime5 = '2018-08-01T00:00:00:00Z'
    endtime5 = '2018-08-05T23:59:59:00Z'
    domain = domains[j]
    res3 = os.popen(
        """python aliyun_CDN.py Action=DescribeDomainsUsageByDay DomainName=%s StartTime=%s EndTime=%s""" % (
            domain, starttime3, endtime3)).readlines()
    res4 = os.popen(
        """python aliyun_CDN.py Action=DescribeDomainsUsageByDay DomainName=%s StartTime=%s EndTime=%s""" % (
            domain, starttime4, endtime4)).readlines()
    res5 = os.popen(
        """python aliyun_CDN.py Action=DescribeDomainsUsageByDay DomainName=%s StartTime=%s EndTime=%s""" % (
            domain, starttime5, endtime5)).readlines()

    c3 = json.loads(res3[0])["UsageTotal"]
    c4 = json.loads(res4[0])["UsageTotal"]
    c5 = json.loads(res5[0])["UsageTotal"]

    a1 = float(c3["MaxBps"]) / 1000 / 1000
    a2 = float(c3["TotalTraffic"]) / 1024 / 1024
    a3 = float(c4["MaxBps"]) / 1000 / 1000
    a4 = float(c4["TotalTraffic"]) / 1024 / 1024
    a5 = float(c5["MaxBps"]) / 1000 / 1000
    a6 = float(c5["TotalTraffic"]) / 1024 / 1024

    rb = open_workbook(filename)
    new_row = rb.sheets()[0].nrows
    write_to_xls(filename, new_row, 0, domain)
    write_to_xls(filename, new_row, 1, a1)
    write_to_xls(filename, new_row, 2, a2)
    write_to_xls(filename, new_row, 3, a3)
    write_to_xls(filename, new_row, 4, a4)
    write_to_xls(filename, new_row, 5, a5)
    write_to_xls(filename, new_row, 6, a6)
    j = j + 1


