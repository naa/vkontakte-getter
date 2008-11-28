#!/usr/bin/python
# -*- coding: utf-8 -*-
from BeautifulSoup import BeautifulSoup
from datetime import *
from cgi import escape
import cookielib, urllib2, urllib, re, os, codecs
import sys

import string,cgi,time
from os import curdir, sep
from BaseHTTPServer import BaseHTTPRequestHandler, HTTPServer
#import pri


cj = cookielib.CookieJar()
opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cj))
opener.addheaders = [('User-agent', 'Mozilla/5.0')]

def transform(data):
	res=''
	template='<?xml version="1.0" encoding="utf-8"?>\
	<rss version="2.0">\
	<channel><title>Новости друзей</title>\
	<link>http://vkontakte.ru/news.php</link>\
	<description>Новости друзей</description>\
	<language>ru-RU</language><lastBuildDate>%(date)s</lastBuildDate>' % {'date':datetime.now()}
        res=template
	soup=BeautifulSoup(data)
	days = soup.findAll('div','feedDayWrap')#attrs={'style':'padding: 10px 10px 20px 10px;'})
	theday=date.today()
	for day in days:
		daydiv=day.findNextSibling('div')
		items=daydiv.findAll('table','feedTable')
		for item in items:
			time = datetime.strptime(item.find('td','feedTime').string.strip(),"%H:%M").time()
			story = item.find('td','feedStory')
			for aTag in story.findAll('a'):
				aTag['href']='http://vkontakte.ru/'+aTag['href']
			name = story.first().string
			text = story.first().next.next
			dt=datetime.combine(theday,time)
			res=res+ '<item><title>%(name)s %(text)s</title><link></link><description>%(story)s</description><pubDate>%(date)s</pubDate><guid></guid></item>' % {'name':str(name),'text':str(text),'story':escape(str(story)),'date':dt.strftime('%a, %d %b %Y %H:%M:%S -0000')}

		theday = theday-timedelta(days=1)
	
	res=res+ '</channel></rss>'
        return res

class MyHandler(BaseHTTPRequestHandler):

	def do_GET(self):
		try:
			self.send_response(200)
			self.send_header('Content-type',	'application/rss+xml')
			self.end_headers()
			data=opener.open('http://vkontakte.ru/news.php').read()    
			self.wfile.write(transform(data))
			return
                
		except IOError:
			self.send_error(404,'File Not Found: %s' % self.path)
     

def main():
    params = urllib.urlencode({'email': sys.argv[1],'pass': sys.argv[2].encode('cp1251')})
    try:
	    auth_res=opener.open("http://vkontakte.ru/login.php?%s" %params).read()
	    if re.search(u'пароль неверный'.encode('cp1251'),auth_res):
		    print "Ошибка авторизации"
		    sys.exit(1)
    except:
	    print "Ошибка соединения"
	    sys.exit(1)

    try:
        server = HTTPServer(('', 9999), MyHandler)
        print 'started httpserver...'
        server.serve_forever()
    except KeyboardInterrupt:
        print '^C received, shutting down server'
        server.socket.close()

if __name__ == '__main__':
    main()

