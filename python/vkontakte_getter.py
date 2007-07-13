#!/usr/bin/python
# vim: set fileencoding=utf8 :
# vim: set ts=4 :


from HTMLParser import HTMLParser
import StringIO
import sys,formatter,StringIO,htmllib,string
from urllib import urlretrieve,urlcleanup
import cookielib, urllib2, urllib, os, threading
#import Image, ImageDraw

from math import sin,cos,pi
import math
import re
from Tkinter import *
import tkGui
import webbrowser
import tkMessageBox
import MultipartPostHandler
import codecs
import traceback

class DataHolder:
	"""	 Хранит все загруженные страницы в каталогах data/<id>
	"""
	def __init__(self,folder):
		self.folder=folder
		

	def load_page(self,idnum,page_name):
		params = urllib.urlencode({'id': idnum})
		return get_opener().open("http://vkontakte.ru/%s?%s" % (page_name,params))

	def get_page(self,idnum,name,pause):
			num=1
			while not os.path.exists(self.folder+idnum+'/'+name+'.html'):
				time.sleep(num*pause)
				r2=self.load_page(idnum,name+".php")
				self.put_page(idnum,r2,name)
				num+=1
				if num>11:
						raise Exception('can\'t download '+str(idnum)) 
			return open(self.folder+idnum+'/'+name+'.html')
		
			
	def get_friends_of(self, idnum):
		return self.parse_friends_page(self.get_page(idnum,'friend',1))

	def get_personal_of(self, idnum):
		return self.parse_personal_page(self.get_page(idnum,'profile',3))	

	def put_page(self, idnum, page,name):
		if not os.path.exists(self.folder+idnum):
			os.makedirs(self.folder+idnum)
		data=page.read()
		if not re.search(u'Слишком быстро...'.encode('cp1251'),data):
				out=open(self.folder+idnum+'/'+name+'.html','w')
				out.write(data)
				out.close()
#		else:
#				print data

	def delete_all(self):
		for root, dirs, files in os.walk(self.folder, topdown=False):
		    for name in files:
		        os.remove(os.path.join(root, name))
		    for name in dirs:
		        os.rmdir(os.path.join(root, name))

	def parse_personal_page(self,fpage):
		lines=fpage.read()
		if re.search(u'пароль неверный'.encode('cp1251'),lines):
				raise Exception('Wrong password')
		m = re.compile('<title>[^<\|]*\| (?P<name1>[^ ]*)(?:([^<]* )(?P<name2>[^< ]*)|\s*)</title>').search(lines)
		if m:
			name=m.group('name1').rstrip().lstrip().decode('cp1251')
			if	m.group('name2'):
				name=name+' '+m.group('name2').rstrip().lstrip().decode('cp1251')
		else:
			return None			

		m = re.compile('<a href=("|\')friend.php\?id=(?P<id>\d*)("|\')>[^<]*</a>').search(lines)
		if m:
				idnum=m.group('id').rstrip().lstrip().decode('cp1251')
		else:		
			return None			

#		print idnum,name
		pdata=PersonData(idnum,name)

		m=re.compile('<a href=\'search.php\?uid=\d*\'>(?P<univer>[^<]*)</a>').search(lines)
		if m and m.group('univer'):
			pdata.univer=m.group('univer').rstrip().lstrip().decode('cp1251')

		m=re.compile('<a href=\'search.php\?uid=\d*&year=\d*\'> \'(?P<year>\d*)</a>').search(lines)
		if m and m.group('year'):
			pdata.year=m.group('year').rstrip().lstrip().decode('cp1251')


		m=re.compile('<a href=\'search.php\?fid=\d*\'>(?P<faculty>[^<]*)</a>').search(lines)
		if m and m.group('faculty'):
			pdata.faculty=m.group('faculty').rstrip().lstrip().decode('cp1251')

		m=re.compile('<a href=\'search.php\?cid=\d*\'>(?P<dept>[^<]*)</a>').search(lines)
		if m and m.group('dept'):
			pdata.dept=m.group('dept').rstrip().lstrip().decode('cp1251')


		regex=re.compile('<a href=\'club(?P<idnum>\d*)\'>(?P<name>[^<]*)</a>')
		m=regex.search(lines)
		while m:
			group=GroupData(m.group('idnum'),m.group('name').decode('cp1251')
)
			group.members[pdata.idnum]=pdata
			pdata.groups[group.idnum]=group
			m=regex.search(lines,m.end())

		regex=re.compile('<a href=\'search.php\?f=1&f\d{1}=[^\']*\'>(?P<name>[^<]*)</a>')
		m=regex.search(lines)
		while m:
			name=m.group('name').decode('cp1251').lower().lstrip().rstrip()

			if len(name)>0:
				inter=Interest(name)
				inter.members[pdata.idnum]=pdata
				pdata.interests[inter.name]=inter
			m=regex.search(lines,m.end())

		regex=re.compile('<td class=[^>]*>\s*'+u'Веб-сайт:'.encode('cp1251')+'\s*</td>\s*<td class="data">\s*<div class="dataWrap">\s*<a href=["\']+(?P<url>[^"\']*)[\'"]+>[^<]*</a>')
		m=regex.search(lines)
		if m:
				url=m.group('url').decode('cp1251').strip()
				if len(url)>0:
						pdata.homepage=url
	
		regex=re.compile('<td class=[\'"]+label[\'"]+>ICQ:</td>\s*<td class=[\'"]+data[\'"]+>\s*<div class=[\'"]+dataWrap[\'"]+>\s*(?P<icq>[^<]*)</div>')
		m=regex.search(lines)
		if m and m.group('icq'):
				icq=m.group('icq').decode('cp1251').strip()
				icq=''.join(re.findall('\d+',icq))
				if len(icq)>0:
						pdata.icq=icq
		
		return pdata


	def parse_friends_page(self,fpage):
	    lines=fpage.read()
	
	    regex = re.compile(
			'(?:<dd[^<]*\s*<a href="profile.php\?id=(?P<id>\d*)">(?P<name>[^<]*)</a>\s*|<dd id="friendShownName_(?P<id1>\d*)">\s*(?P<name1>[^<]*)\s*)'+
	        '\s*</dd>\s*'+
			'(?:<dt>[^<]*</dt>\s*'+
			'<dd>(?:(?P<uni>[^<\']*)\'(?P<year>\d*)|(?P<univer>[^<\']*\s*))</dd>\s*<dt>[^<]*</dt>\s*'+
	        '<dd>(?P<faculty>[^<]*)</dd>\s*<dt>[^<]*</dt>\s*'+
	        '(<dd>(?P<dept>[^<]*)\s*</dd>|)|)')
#	    reg4 = re.compile('(?:<dd[^<]*\s*<a href="profile.php\?id=(?P<id>\d*)">(?P<name>[^<]*)</a>\s*|<dd id="friendShownName_(?P<id1>\d*)">\s*(?P<name1>[^<]*)\s*)'+'\s*</dd>\s*'+'(?:<dt>[^<]*</dt>\s*'+'<dd>(?:(?P<uni>[^<\']*)\'(?P<year>\d*)|(?P<univer>[^<\']*\s*))</dd>\s*<dt>[^<]*</dt>\s*'+'<dd>(?P<faculty>[^<]*)</dd>\s*<dt>[^<]*</dt>\s*'+ '(<dd>(?P<dept>[^<]*)\s*</dd>|)|)')



	    m=regex.search(lines)
	    persons={}
	    while m:
			if m.group('id'):	
				idnum=m.group('id').rstrip().lstrip()
				name=m.group('name').rstrip().lstrip().decode('cp1251')
			else:	
				idnum=m.group('id1').rstrip().lstrip()
				name=m.group('name1').rstrip().lstrip().decode('cp1251')
				
			pdata=PersonData(idnum,name)
			if m.group('uni') or m.group('univer'):
				if m.group('uni'):
					pdata.univer=m.group('uni').rstrip().lstrip().decode('cp1251')
	
					pdata.year=m.group('year').rstrip().lstrip().decode('cp1251')
	
				else:	
					pdata.univer=m.group('univer').rstrip().lstrip().decode('cp1251')
				
				pdata.faculty=m.group('faculty').rstrip().lstrip().decode('cp1251')
				if m.group('dept'):	
						pdata.dept=m.group('dept').rstrip().lstrip().decode('cp1251')
	
	#		try:
	#		except IndexError:
	#			print idnum,name	
			persons[idnum]=pdata
			m=regex.search(lines,m.end())
	    return persons

class AddFof(threading.Thread):
	def set_params(self,k,v,opener,friends_of_friends,fof_num,fof_names):
		self.k=k
		self.v=v
		self.opener=opener
		self.fof=friends_of_friends
		self.fof_num=fof_num
		self.fof_names=fof_names
		
	def run(self):
		for k2, v2 in data_holder.get_friends_of(self.k).iteritems():
			self.fof[k2]=v2
			if self.fof_num.has_key(k2):
				self.fof_num[k2]+=1
				self.fof_names[k2]+=' ('+self.v+' '+str(self.k)+') '
			else:
				self.fof_num[k2]=1
				self.fof_names[k2]=''
		
	

def print_friends_of_friends():
	"""Печатает список друзей друзей (для тестов, сейчас не используется)
	"""
	friends_of_friends={}
	fof_num={}
	fof_names={}
	threads={}
	my_friends=data_holder.get_friends_of('')
	for k,v in my_friends.iteritems():
		threads[k]=AddFof()
		threads[k].set_params(k,v,get_opener(),friends_of_friends,fof_num,fof_names)
		threads[k].start()
#		threads[k].join()

	for k,th in threads.iteritems():
		if th.isAlive():
			th.join()
	
	list_of_fof=[]
	num_list_of_fof=[]
	name_list_of_fof=[]
	for k,v in friends_of_friends.iteritems():
		if not my_friends.has_key(k):
			list_of_fof.append(v+' num of comm friends:'+str(fof_num[k]))
			num_list_of_fof.append('num of comm friends:%(#)03d %(name)s' % {'#':fof_num[k],'name':v})
			#name_list_of_fof.append(v+fof_names[k])
	num_list_of_fof.sort()
	num_list_of_fof.reverse()	
	list_of_fof.sort()
	print('List of friends of friends:')
	for k in list_of_fof:
		print k
	print('***')
	print('List of friends of friends (number of common friends):')	
	for k in num_list_of_fof:
		print k
		
#	for k in name_list_of_fof:
#		print k

class Interest:
	"""Интерес (музыка, политика и т.п.)
	"""

	def __init__(self,name):
		self.name=name
		self.members={}

	def add_member(self,person):
		self.members[person.idnum]=person

	def get_size(self):
		return len(self.members.keys())

class GroupData(Interest):
	""" Информация о группе
	"""	
	def __init__(self,idnum,name):
		Interest.__init__(self,name)
		self.idnum=idnum

class PersonData:
	"""Представляет собой информацию о человеке
	"""
	def __init__(self,idnum,name,drawnumber=0):
		self.name=name
		self.idnum=idnum
		self.faculty=''
		self.univer=''
		self.dept=''
		self.year=''
		self.friends={}
		self.drawnumber=drawnumber
		self.x=0
		self.y=0
		self.tr=False
		self.total_friends_num=0
		self.groups={}
		self.interests={}
		self.homepage=''
		self.icq=''

	def is_drawn(self):
			return self.x!=0 or self.y!=0

	def print_me(self):
			print self.idnum,self.name,self.univer,self.year,self.faculty,self.dept

	def jscript_repr(self):
			if '\',\''.join(self.friends.keys()) != '':
					fr_ids='[\''+'\',\''.join(self.friends.keys())+'\']'
			else:
					fr_ids='[]'
			if self.tr:
				right=1
			else :
				right=0
			return "new Person(\'%(name)s\', \'%(idnum)s\', %(x)d, %(y)d, %(tr)s, %(fr_ids)s)\n" % {'name':self.name,'idnum':self.idnum,'x':self.x,'y':self.y,'fr_ids':fr_ids,'tr':right}

#	def html_repr(self):
#			if self.tr:
#					return '<div style=\"{position: absolute; left:%(x)dpx; top:%(y)dpx; visibility: visible; }\" ><a onmouseover=\"show(\'%(id)s\');\" onmouseout=\"hide(\'%(id)s\');\" id=\'p%(id)s\' href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s (%(num)s)</a></div>' % {'x':self.x-80,'y':self.y,'name':self.name,'id':self.idnum,'num':len(self.friends.keys())} 
#			else:				
#					return '<div style=\"{position: absolute; left:%(x)dpx; top:%(y)dpx; visibility: visible; }\" ><a onmouseover=\"show(\'%(id)s\');\" onmouseout=\"hide(\'%(id)s\');\" id=\'p%(id)s\' href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s (%(num)s)</a></div>' % {'x':self.x,'y':self.y,'name':self.name,'id':self.idnum,'num':len(self.friends.keys())} 
#					return '<div style=\"{position: absolute; left:%(x)dpx; top:%(y)dpx; visibility: visible; }\" onmouseover=\"show(\'%(id)s\');\" onmouseout=\"hide(\'%(id)s\');\" id=\'p%(id)s\'><a onmouseover=\"this.style.color=red;\" onmouseout=\"this.style.color=#808080;\" href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s (%(num)s)</a></div>' % {'x':self.x,'y':self.y,'name':self.name,'id':self.idnum,'num':len(self.friends.keys())} 

class Circle:
		def __init__(self,center_x,center_y,rad=0):
				self.rad=rad
				self.center_x=center_x
				self.center_y=center_y
				self.members=[]

		def add(self,person):
				self.members.append(person)

		def set_pos(self):
				i=0
				num=len(self.members)
				if num==0 or self.rad==0:
						return
				dy=self.rad*4/num
				self.members.sort(key=lambda x: x.name)
				for pers in self.members:
						if i<=num/2:
								pers.y=self.center_y-self.rad+i*dy
								angle=math.asin(float(pers.y-self.center_y)/self.rad)
								pers.x=self.center_x+self.rad*math.cos(angle)
						else:
								pers.y=self.center_y-self.rad+(i-num/2)*dy
								angle=math.asin(float(pers.y-self.center_y)/self.rad)
								pers.x=self.center_x-self.rad*math.cos(angle)
								pers.tr=True

						i+=1

		def adjust_radius(self,font_height,in_rad):
				self.rad=in_rad
				num=len(self.members)
				rad=num*font_height/4
				if rad>self.rad:
						self.rad=rad
				return self.rad


class FineImageProducer:
		def __init__(self,self_id,upload=1,size=1200,center_y=650,callback=None):
			self.size=size	
			self.dept_circle=Circle(size/2,center_y,70)	
			self.fac_circle=Circle(size/2,center_y,200)
#			self.year_circle=Circle(size/2,250)
			self.univ_circle=Circle(size/2,center_y,350)
			self.others_circle=Circle(size/2,center_y,500)
			self.center_y=center_y
			self.myself = data_holder.get_personal_of(self_id)
			self.my_friends=data_holder.get_friends_of(self_id)
			self.myself.friends=self.my_friends
			self.font_size=10
			self.callback=callback
			self.exit=False
			self.friends_of_friends={}
			self.upload=upload
		
		def fill_circles(self):
			total=len(self.my_friends.keys())
			num=0
			for k in self.my_friends.keys():
					v=data_holder.get_personal_of(k)
					if v:
						self.my_friends[k]=v
					v=self.my_friends[k]	
					num+=1
					if v.dept == self.myself.dept:	
							self.dept_circle.add(v)
					elif v.univer==self.myself.univer and v.faculty==self.myself.faculty:
							self.fac_circle.add(v)
#					elif v.univer==self.myself.univer and v.year==self.myself.year:
#							self.year_circle.add(v)
					elif v.univer==self.myself.univer:
							self.univ_circle.add(v)
					else:
							self.others_circle.add(v)
		
					for k2,v2 in data_holder.get_friends_of(k).iteritems():
							v.total_friends_num+=1
							if self.my_friends.has_key(k2):
								v.friends[k2]=self.my_friends[k2]
							elif self.friends_of_friends.has_key(k2):
								self.friends_of_friends[k2].friends[k]=v
							elif self.myself.idnum != k2:
								self.friends_of_friends[k2]=v2 
							
					if self.callback:
							self.callback(num,total)
					if self.exit:
							exit()

		def calc_positions(self):
			rad=self.dept_circle.adjust_radius(self.font_size,70)+150
			rad=self.fac_circle.adjust_radius(self.font_size,rad)+150
#			self.year_circle.adjust_radius(self.font_size)
			rad=self.univ_circle.adjust_radius(self.font_size,rad)+150
			rad=self.others_circle.adjust_radius(self.font_size,rad)+150

			self.dept_circle.set_pos()
			self.fac_circle.set_pos()
#			self.year_circle.set_pos()
			self.univ_circle.set_pos()
			self.others_circle.set_pos()

		def output_js(self,output):
				output.write(u'my_name=\'')
				output.write(self.myself.name)
				output.write(u'\';\npersons={')
				for k,v in self.my_friends.iteritems():
						output.write(u'\''+k+u'\':')
						output.write(v.jscript_repr())
						output.write(u',\n')
				output.write(u'\'dummy\':\'dummy\'\n')		
				output.write(u'};')		
				output.write(u'</script>')
		
		def output_caption(self,output,circle,text):	
			x,y=circle.center_x-50,circle.center_y-circle.rad-30
			output.write('<div class=\"cir_head\" style=\"{position:absolute; left:%(x)d; top:%(y)d}\">%(text)s</div>\n' % {'x':x, 'y' :y, 'text':text})


		def output_html(self,output):
				output.write('<div id="group0" style="width:1200px;height:1200px;"><canvas id="canvas" width=1200px height=1200px></canvas> \n')

				self.output_caption(output,self.dept_circle,u'Кафедра')
				self.output_caption(output,self.fac_circle,u'Факультет')
				self.output_caption(output,self.univ_circle,u'Университет')
				self.output_caption(output,self.others_circle,u'Друзья')
#				for k,v in self.my_friends.iteritems():
#						output.write(v.html_repr())
#						output.write('\n')
				output.write('</div>\n')

		def draw_circle(self):
			output = codecs.open('tmp','w','utf-8')
			draw_map={}
			self.fill_circles()
			self.calc_positions()
		
			self.output_js(output)
			output.write('</script>\n')
			self.output_html(output)
	
			self.output_groups(output)

			self.output_links(output)

			output.write('<div id=\'group1\' style="{position:absolute; top:50px; display:none;};">\n')
			self.output_statistics(output)
			output.write('<div style="{display:none; position:absolute; top:50px;};" id=\'group2\'>\n<textarea cols=100 rows=32 readonly>\n')
			output.write('<div id=\'group1\'>\n')
			self.output_statistics(output)
			output.write('</textarea>\n')		
#			output.writelines(open('foot.html').readlines())

			output.close()

			output=open('result.html','w')
			output.writelines(urllib.urlopen('http://vkontakte.net.ru/head.html').readlines())
			output.writelines(open('tmp').readlines())
			output.close()
			
			if self.upload==1:
				self.upload_results('tmp')	
			os.remove('tmp')

			self.output_icq()

		def upload_results(self,filename):
			cookies = cookielib.CookieJar()

			opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookies), MultipartPostHandler.MultipartPostHandler)
			params = {'upload_file':open(filename,'rb'),'submit':'true','idnum':self.myself.idnum,'username':self.myself.name}

			opener.open('http://vkontakte.net.ru/upload.cgi', params)

		def output_top_n(self,output,key_list,out_dict,out_num,n):
			for k in key_list[:n]:
				output.write('<tr><td><a href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s</a></td><td>%(num)d</td></tr>\n'% {'id':k,'name':out_dict[k].name,'num':out_num(k)})
			output.write('</table>\n')


		def output_most_friendly(self,output):
			key_list=self.my_friends.keys()
			key_list.sort(key=lambda x: self.my_friends[x].total_friends_num,reverse=True)
			output.write(u'<h3>Самые дружелюбные</h3>\n<table>\n')
			self.output_top_n(output,key_list,self.my_friends,lambda x:self.my_friends[x].total_friends_num,5)

		def output_most_common(self,output):		
			key_list=self.my_friends.keys()
			key_list.sort(key=lambda x: len(self.my_friends[x].friends.keys()),reverse=True)
			output.write(u'<h3>Больше всего общих друзей:</h3>\n<table>\n')

			self.output_top_n(output,key_list,self.my_friends,lambda x:len(self.my_friends[x].friends.keys()),5)

		def output_most_unknown(self,output):		
			key_list=self.friends_of_friends.keys()
			key_list.sort(key=lambda x: len(self.friends_of_friends[x].friends.keys()),reverse=True)
			output.write(u'<h3>Больше всего общих друзей (лично не знакомы):</h3>\n<table>\n')
			self.output_top_n(output,key_list,self.friends_of_friends,lambda x:len(self.friends_of_friends[x].friends.keys()),5)

	
	
		def output_statistics(self,output):
			output.write(u'<h2>Статистика</h2>\n')
			output.write((u'Всего друзей: %d<br>\n' % len(self.my_friends.keys())))
			output.write((u'Всего друзей друзей: %d<br>\n' % (len(self.my_friends.keys()) + len(self.friends_of_friends.keys()))))
			if len(self.my_friends.keys()) != 0:	
					output.write((u'Среднее число друзей у друзей: %f<br>\n' % (float(reduce(lambda x,y: x+self.my_friends[y].total_friends_num,self.my_friends.keys(),0)) / (len(self.my_friends.keys())))))


			self.output_most_friendly(output)
			self.output_most_common(output)
			self.output_most_unknown(output)
			output.write('</div>\n')

		def output_groups(self,output):
			groups={}
			interests={}
			for k in self.my_friends.keys():
				person=data_holder.get_personal_of(k)
				if not person:
						person=self.my_friends[k]
				for gid,group in person.groups.iteritems():
					if groups.has_key(gid):
						groups[gid].members[k]=person
					else:	
						groups[gid]=group	
				for name,inter in person.interests.iteritems():
					if interests.has_key(name):
						interests[name].members[k]=person
					else:	
						interests[name]=inter	

			key_list=groups.keys()
			output.write('<table id=\'group3\' style="{display:none;};" width=750px>\n <tr><td>')
			output.write('<div height=50px> &nbsp; </div><br>')
			output.write(u'<h2>Популярные группы:</h2>\n')
			key_list.sort(key=lambda x: groups[x].get_size(),reverse=True)
		  	for k in key_list[:5]:
				output.write('\n<h3><a href="http://vkontakte.ru/club%(gid)s">%(name)s (%(num)d)</a></h3>\n' % {'gid':groups[k].idnum,'name':groups[k].name,'num':groups[k].get_size()})
				for pid,person in groups[k].members.iteritems():
						output.write('<a href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s</a>, '% {'id':pid,'name':person.name})
	
			output.write('</td></tr></table>')

			key_list=interests.keys()
			output.write('<table id=\'group4\' style="{display:none;};" width=750px>\n <tr><td>')
			output.write('<div height=50px> &nbsp; </div><br>')
			output.write(u'<h2>Популярные интересы:</h2>\n')
			key_list.sort(key=lambda x: interests[x].get_size(),reverse=True)
		  	for k in key_list[:10]:
				output.write('\n<h3>%(name)s (%(num)d)</h3>\n' % {'name':interests[k].name,'num':interests[k].get_size()})
				for pid,person in interests[k].members.iteritems():
						output.write('<a href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s</a>, '% {'id':pid,'name':person.name})
	
			output.write('</td></tr></table>')

		def output_links(self,output):
			key_list=self.my_friends.keys()
			output.write('<table id=\'group5\' style="{display:none;};" width=750px>\n <tr><td>')
			output.write('<div height=50px> &nbsp; </div><br>')
			output.write(u'<h2>Странички друзей:</h2>\n')
			output.write('</td></tr>\n')
			key_list.sort(key=lambda x: self.my_friends[x].name)
			for k in key_list:
					person=self.my_friends[k]
					if len(person.homepage)>0:
							output.write('<tr><td><a href=\"http://vkontakte.ru/profile.php?id=%(id)s\">%(name)s</a>: <a href=\"%(url)s\">%(url)s</a>\n'% {'id':person.idnum,'name':person.name, 'url':person.homepage})
			output.write('</table>')

		def output_icq(self):
			out=codecs.open('icqnumbers.clb','w','cp1251')
			members=self.dept_circle.members
			members.sort(key=lambda x:x.name)
			def print_out(str1,members):
					for p in members:
							if len(p.icq)>4:
								out.write(str1)
								out.write(u';')
								out.write(p.icq)
								out.write(u';')
								out.write(p.name)
								out.write(u';;;')
								out.write('\n')

			print_out(self.myself.dept,members)	
			members=self.fac_circle.members
			members.sort(key=lambda x:x.name)
			print_out(self.myself.faculty,members)	
			members=self.univ_circle.members
			members.sort(key=lambda x:x.name)
			print_out(self.myself.univer,members)	
			members=self.others_circle.members
			members.sort(key=lambda x:x.name)
			print_out(u'Остальные',members)
			out.close()
				
		


class MainThread(threading.Thread):
		def set_param(self,person,callback,parent,fip):
				self.person=person
				self.callback=callback
				self.fip=fip
				self.parent=parent

		def run(self):
			try:	
				self.fip.draw_circle() 
				webbrowser.open_new_tab('result.html')
			except:
				out=open("error.log","w")
				traceback.print_exc(None)
				traceback.print_exc(None,out)
				out.close()
				self.callback(111,111,error=1)
					

		def exit(self):
				self.fip.exit=True

class MainDialog(tkGui.MyDialog):
		def __init__(self,master):
				tkGui.MyDialog.__init__(self,master)
				self.mt=None

		def ok(self):
				try: 
		
						params = urllib.urlencode({'email': self.e1.get(), 'pass': self.e2.get()})
						if self.reload.get()==1:
							data_holder.delete_all()							
						person = data_holder.parse_personal_page(get_opener().open("http://vkontakte.ru/login.php?%s" %params))
						time.sleep(3)
						self.mt=MainThread()
						
						fip = FineImageProducer(person.idnum,callback=self.callback,upload=self.upload.get()) 
						self.mt.set_param(person,self.callback,self,fip)
						self.mt.start()
				except Exception, inst:
						if inst.args[0]=='Wrong password':
								tkMessageBox.showwarning(
										u"Ошибка при авторизации",		
										u"Проверьте, пожалуйста, email и пароль."
						           )
				except:		
								tkMessageBox.showwarning(
						                u"Возникла ошибка при загрузке страниц с vkontakte.ru",
										u"Если вы уверены, что ошибка именно в программе,\n а не вызвана падением сайтов vkontakte.ru или vkontakte.net.ru,\n отправьте, пожалуйста, баг-репорт:\n http://code.google.com/p/vkontakte-getter/issues/list"
				           )
								out=open("error.log","w")
								traceback.print_exc(None,out)
								out.close()

				
		def callback(self,num,total,error=None):
				if not error:
						self.message.set(u'Загружаются страницы друзей: %d из %d' % (num,total))
				else:
						self.message.set(u"Возникла ошибка при загрузке страниц с vkontakte.ru\n"+
										u"Если вы уверены, что ошибка именно в программе,\nа не вызвана падением сайтов vkontakte.ru или vkontakte.net.ru,\nотправьте, пожалуйста, баг-репорт:\nhttp://code.google.com/p/vkontakte-getter/issues/list\nПодробная информация в файле error.log")

#				print 'callback %d %d' % (num,total)
				#self.frame.update()

		def cancel(self):
#				print 'Going to exit'
				if self.mt:
						self.mt.exit()
				sys.exit()


cj = cookielib.CookieJar()
data_holder=DataHolder('data/')
def get_opener():
	newopener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cj))
	newopener.addheaders = [('User-agent', 'Mozilla/5.0')]
	return newopener

import time
time.sleep(1)
#print_num_of_common_friends(opener)
#print_friends_of_friends()

root=Tk()
root.title(u'vkontakte-getter')
d=MainDialog(root)
root.mainloop()


