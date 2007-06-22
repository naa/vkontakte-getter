#!/usr/bin/python
# vim: set fileencoding=utf8 :
# vim: set ts=4 :

# File: tk_gui.py
from Tkinter import *


class MyDialog:
	def __init__(self, master):
			
		self.frame=Frame(master)
		frame=self.frame
		self.frame.grid()
		
		Label(frame, text=u'Введите свой email и пароль,\n которые вы используете на сайте\n vkontakte.ru, чтобы построить карту друзей').grid(row=0,columnspan=2)
		Label(frame, text=u"Email:").grid(row=1,sticky=W)
		Label(frame, text=u"Пароль:").grid(row=2,sticky=W)

		self.message = StringVar()
		self.info=Label(master, textvariable=self.message)
		self.info.grid(row=3,columnspan=2)
		self.message.set("   ")

		self.e1 = Entry(frame)
		self.e2 = Entry(frame,show="*")
		
		self.e1.grid(row=1, column=1)
		self.e2.grid(row=2, column=1)

		w = Button(frame, text="OK", width=10, command=self.ok, default=ACTIVE)
#		w.pack(side=LEFT, padx=5, pady=5)
		w.grid(row=4)
		w = Button(frame, text="Exit", width=10, command=self.cancel)
#		w.pack(side=LEFT, padx=5, pady=5)
		w.grid(row=4,column=1)

	def ok(self):
		pass

	def cancel(self):
		pass

