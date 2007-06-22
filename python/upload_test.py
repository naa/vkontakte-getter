#!/usr/bin/python
# vim: set fileencoding=utf8 :
# vim: set ts=4 :


import sys,formatter,StringIO,htmllib,string
from urllib import urlretrieve,urlcleanup
import cookielib, urllib2, urllib, os, threading
import webbrowser
import MultipartPostHandler

filename='uploadfile.rar'

cookies = cookielib.CookieJar()

opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookies),
                                MultipartPostHandler.MultipartPostHandler)
opener.open('http://vkontakte.net.ru/test/index.cgi')
params = {'upload_file':open(filename,'rb'),'submit':'true'}

f=opener.open('http://vkontakte.net.ru/test/index.cgi', params)

print f.readlines()
