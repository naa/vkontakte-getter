//onload = prepareRequest();

image = new Image();
image.src = "loader2.gif";

var myself;
var persons={};
function trim(str) { return str.replace(/^\s+|\s+$/, ''); }

function urlenc(s) {
	return s.replace('%','%25','g').replace('&','%26','g').replace('+','%2B','g').replace('?','%3F','g');
}

function prepareRequest() {
	var http_request = false;
	if (window.XMLHttpRequest) { // Mozilla, Safari,...
		http_request = new XMLHttpRequest();
	} else if (window.ActiveXObject) { // IE
		try {
			http_request = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				http_request = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {}
		}
	}
	if (!http_request) {
		alert('Ошибка при создании XMLHTTP');
		window.location = '_fgb.php';
		return false;
	}
	return http_request;
}

function getPage(url,idnum,onLoad) {

	req = prepareRequest();
	this.onLoad=onLoad;
	this.idnum=idnum;
	if (req) {
		document.getElementById('loading').style.display = "block"; 
		netscape.security.PrivilegeManager.enablePrivilege("UniversalBrowserRead");
		req.onreadystatechange = processReqChange;
		req.open('POST', url); //?POST
		req.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		req.send('id='+idnum);
	}
}

function processReqChange() {

	//	document.getElementById('shedule').innerHTML = 	"<br /><br />"+stat(req.readyState); 
	if (req.readyState == 4) {

		if (req.status == 200) {
			document.getElementById('loading').style.display = "none"; 
			onLoad(idnum,req.responseText);
		} else {
			alert("Не удалось получить данные:\n" + req.statusText);
		}
	}  
}

function Person(name,idnum){
	this.name=name;
	this.idnum=idnum;
	this.friends_ids={};
	this.x=0;
	this.y=0;
	this.univer='';
	this.dept='';
	this.faculty='';
	this.year='';
}
function draw(ctx,idnum,textcolor) {
	document.getElementById('p'+idnum).style.color=textcolor;
	var sel=persons[idnum];
	var prs_id='';
	var pers;
	ctx.beginPath();
	ctx.arc(sel.x, sel.y, 2, 0, Math.PI*2, true);
	for (var i=0; i<sel.friends_ids.length; i++) {
		prs_id=sel.friends_ids[i];
		pers=persons[prs_id];
		document.getElementById('p'+prs_id).style.color=textcolor;
		ctx.moveTo(sel.x,sel.y);
		ctx.lineTo(pers.x,pers.y);
		ctx.arc(pers.x, pers.y, 2, 0, Math.PI*2, true);

	}	
	ctx.moveTo(sel.x,sel.y);
	ctx.closePath();
	ctx.stroke();
}
function show(idnum) {
	var canvas = document.getElementById('canvas');
	var ctx = canvas.getContext('2d');
	ctx.strokeStyle='#0000ff';
	draw(ctx,idnum,'#ff0000');
}
function hide(idnum) {
	var canvas = document.getElementById('canvas');
	var ctx = canvas.getContext('2d');
	ctx.fillStyle = 'white';
	ctx.fillRect(0, 0, 1200, 1200);
	ctx.strokeStyle='white';
	draw(ctx,idnum,'#808080');
}

function onLoadPersonalPage(idnum,page_text) {
	re =/<title>[^<\|]*\| ([^<]*)<\/title>/g;
	name=re.exec(page_text)[1];
	re=/<a href="friend.php\?id=(\d*)">[^<]*<\/a>/g;	
	idnum = re.exec(page_text)[1];
	person=new Person(name,idnum);
	re=/<a href=\'search.php\?uid=\d*\'>([^<]*)<\/a>/g;
	arr=re.exec(page_text);
	if (arr) { person.univer=arr[1];	}
	re=/<a href=\'search.php\?uid=\d*&year=\d*\'> \'(\d*)<\/a>/g;
	arr=re.exec(page_text);
	if (arr) { person.year=arr[1]; }
	re=/<a href=\'search.php\?fid=\d*\'>([^<]*)<\/a>/g;
	arr=re.exec(page_text);
	if (arr) { person.faculty=arr[1]; }
	re=/<a href=\'search.php\?cid=\d*\'>([^<]*)<\/a>/g;
	arr=re.exec(page_text);
	if (arr) { person.dept=arr[1]; }
	myself=person;
	alert(person.name+person.idnum+person.univer+person.year+person.faculty+person.dept);
}

function onLoadMyFriendsPage(idnum,page_text) {
	lastIndex=0;
//test='\n    <a href="profile.php?id=362183">НикиТа (aka WildNick) Емельянов</a>\n\n   </dd>\n\n      <dt>Выпуск:</dt>\n   <dd>ВМИИ им. Дзержинского \'07</dd>   <dt>Факультет:</dt>\n   <dd>Комплексных систем управления</dd>   <dt>Кафедра:</dt>\n   <dd>КСУ ТС ПЛ</dd>\n\n  </dl>';
	for (;;) {
		re=/<a href="profile.php\?id=(\d*)">([^<]*)<\/a>((.*\s*)*?)<\/dl>/mg;
		re.lastIndex=lastIndex;
		res=re.exec(page_text);
		lastIndex=re.lastIndex;
		if (!res) break;
		idnum=res[1];
		name=res[2];
		unidata=res[3];
		person=new Person(name,idnum);
//		alert(unidata);
		if (unidata) {
			unire=/\s*<dt>Выпуск:<\/dt>\s*<dd>([^<]*) '?(\d*)?<\/dd>\s*/mg;
//			test='\n<dt>Выпуск:</dt>\n   <dd>СПбГУ \'06</dd>   <dt>Факультет:</dt>\n   <dd>Филологический</dd>   <dt>Кафедра:</dt>\n   <dd>Английской филологии и перевода</dd>';
			test='\n   </dd>\n\n      <dt>Выпуск:</dt>\n   <dd>СПбГУ \'06</dd>   <dt>Факультет:</dt>\n   <dd>Филологический</dd>   <dt>Кафедра:</dt>\n\n   <dd>Английской филологии и перевода</dd>\n\n  </dl>';
			arr=unire.exec(test);
			unire.lastIndex=0;
			if (arr) {
				person.univer=arr[1];
				if (arr.length>1) {person.year=arr[2];}
			}
			unire=/\s*<dt>Факультет:<\/dt>\s*<dd>([^<]*)\s*<\/dd>\s*/mg;
			arr=unire.exec(unidata);
			unire.lastIndex=0;
			if (arr) { person.faculty=arr[1]; }
			unire=/\s*<dt>Кафедра:<\/dt>\s*<dd>([^<]*)\s*<\/dd>\s*/mg;
			arr=unire.exec(unidata);
			unire.lastIndex=0;
			if (arr) { person.dept=arr[1]; }
		}
		persons[idnum]=person;
	//	alert(person.name+person.idnum+person.univer+person.year+person.faculty+person.dept);
//		break;
	}

	//        '</dd>\s*'+
	//        '(<dt>[^<]*</dt>\s*'+
	//        '<dd>((?P<uni>[^<\']*)\'(?P<year>\d*)|(?P<univer>[^<\']*\s*))</dd>\s*<dt>[^<]*</dt>\s*'+
	//        '<dd>(?P<faculty>[^<]*)</dd>\s*<dt>[^<]*</dt>\s*'+
	//        '<dd>(?P<dept>[^<]*)\s*</dd>|)')

	for (idnum in persons) {
//		alert(person);
		person=persons[idnum];
	document.writeln(person.name+person.idnum+person.univer+person.year+person.faculty+person.dept);
	}
}

function mouseOver(idnum) {
	if (persons[idnum].friends_loaded) {
		show(idnum)
	}else {
	}
}


