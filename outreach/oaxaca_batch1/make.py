from PIL import Image, ImageDraw, ImageFont
F='C:/Users/BL/ruinmytrip/public/assets/fonts/'
INK=(15,27,45); BRAND=(20,184,166); WHITE=(255,255,255); MUTED=(170,184,200); PANEL=(22,37,60)
def font(b,s): return ImageFont.truetype(F+('DejaVuSans-Bold.ttf' if b else 'DejaVuSans.ttf'), s)
def wrap(d,t,f,w):
    out=[];line=''
    for word in t.split():
        x=(line+' '+word).strip()
        if d.textlength(x,font=f)<=w: line=x
        else: out.append(line); line=word
    out.append(line); return out
def slide(name,kicker,title,body='',foot='ruinmytrip.com',W=1080,H=1350,num=None):
    im=Image.new('RGB',(W,H),INK); d=ImageDraw.Draw(im)
    d.rectangle([0,0,18,H],fill=BRAND); p=90
    d.text((p,90),'RuinMyTrip',font=font(1,40),fill=BRAND)
    d.text((p+d.textlength('RuinMyTrip',font=font(1,40))+30,98),kicker.upper(),font=font(0,32),fill=MUTED)
    y=230; tf=font(1,78 if len(title)<60 else 64)
    for l in wrap(d,title,tf,W-2*p): d.text((p,y),l,font=tf,fill=WHITE); y+=tf.size+18
    y+=40; bf=font(0,44)
    for para in body.split('\n'):
        for l in wrap(d,para,bf,W-2*p): d.text((p,y),l,font=bf,fill=(225,232,240)); y+=60
        y+=24
    if num: d.text((W-p-d.textlength(num,font=font(0,32)),H-110),num,font=font(0,32),fill=MUTED)
    d.text((p,H-110),foot,font=font(0,36),fill=BRAND)
    im.save(name+'.png'); return y
# Post 1: carousel, the warnings
slide('p1_1','Oaxaca · Day of the Dead','4 things travelers wish they had known about Day of the Dead in Oaxaca','Swipe. Then add your dates and see who else is going.',num='1/6')
slide('p1_2','Oaxaca · Day of the Dead','The 2026 program is not out yet','As of 1 October the official program for Oaxaca city has not been published. Parade times copied from last year are last year\'s.',num='2/6')
slide('p1_3','Oaxaca · Day of the Dead','The cemetery vigils last all night','In Santa Cruz Xoxocotlán they run on the nights of 31 October and 1 November, outside the center. Plan your ride back before you go.',num='3/6')
slide('p1_4','Oaxaca · Day of the Dead','Ask before you photograph','People and altars, every time. And never use flash.',num='4/6')
slide('p1_5','Oaxaca · Day of the Dead','It gets cold after dark','November nights in the cemetery are cold. Bring a jacket.',num='5/6')
slide('p1_6','Oaxaca · Day of the Dead','Going? See who else will be there','Add the nights you will be in Oaxaca. You get one email when another traveler\'s dates overlap yours. Free, no account needed.\nLink in bio.',num='6/6')
# Post 2: single, the two nights
slide('p2','Oaxaca · Day of the Dead','Two nights, not one','In Santa Cruz Xoxocotlán the cemetery vigils are held on the night of 31 October and the night of 1 November, from about 5 or 6 in the evening until morning.\nWhich night are you going? Link in bio to add your dates.')
# Post 3: solo angle
slide('p3','Oaxaca · Day of the Dead','Going to Oaxaca for Day of the Dead on your own?','Add your dates and see who else will be there on the same nights. We only email you when a real traveler overlaps.\nLink in bio.')
# Post 4: story 1080x1920
slide('story','Oaxaca · Day of the Dead','Going to Oaxaca for Day of the Dead?','31 October to 2 November.\nAdd your dates, see who else is going.\n\n[link sticker here]',W=1080,H=1920)
print('ok')
