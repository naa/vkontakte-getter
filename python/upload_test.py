#!/usr/bin/python
# vim: set fileencoding=utf8 :
# vim: set ts=4 :


import sys,formatter,StringIO,htmllib,string
from urllib import urlretrieve,urlcleanup
import cookielib, urllib2, urllib, os, threading
import webbrowser
import MultipartPostHandler

filename='tkGui.py'

cookies = cookielib.CookieJar()

opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookies),
                                MultipartPostHandler.MultipartPostHandler)
#opener.open('http://vkontakte.net.ru/test/upload.cgi')
params = {'upload_file':open(filename,'rb'),'submit':'true','idnum':'1356'}

f=opener.open('http://vkontakte.net.ru/upload.cgi', params)

print f.readlines()
