import random, datetime as dt
random.seed(2026)
CUSTOMER_IDS=[1,3,4]+list(range(12,20))+list(range(120,161))
now = dt.datetime.now()
out = []
def ins(t, cols, rows):
    if not rows: return
    out.append(f"INSERT INTO `{t}` ({','.join(cols)}) VALUES")
    vals=[]
    for r in rows:
        def fmt(v):
            if v is None: return 'NULL'
            if isinstance(v,(int,float)): return str(v)
            if isinstance(v,dt.datetime): return "'%s'"%v.strftime('%Y-%m-%d %H:%M:%S')
            if isinstance(v,dt.date): return "'%s'"%v.strftime('%Y-%m-%d')
            return "'%s'"%str(v).replace("'","''")
        vals.append("(" + ",".join(fmt(x) for x in r) + ")")
    out.append(",\n".join(vals) + ";\n")

# extra customers
cust_rows=[]
for n in range(120,161):
    vis=random.randint(1,40); spent=round(random.uniform(800,42000),2)
    cust_rows.append((n,"Member %d"%n,"+92300%d"%random.randint(1000000,9999999),"92300%d"%random.randint(1000000,9999999),"member%d@demo.local"%n,random.choice(['regular','regular','vip','member']),
                      vis,random.randint(2,60),spent,round(random.uniform(0,2500),2) if random.random()<.3 else 0.0,
                      (now-dt.timedelta(days=random.randint(0,40))).strftime('%Y-%m-%d %H:%M:%S'),'active',
                      now.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
ins('customers',["id","name","phone","whatsapp","email","category","total_visits","total_hours","total_spent","outstanding_balance","last_visit_at","status","created_at","updated_at"],cust_rows)

tables=[1,2,3,4,5,6,7]
rates={1:300,2:300,3:300,4:300,5:400,6:400,7:450}
rate_type_w=[('hourly',.40),('peak',.15),('off_peak',.20),('night',.10),('vip',.10),('frame',.05)]
def pick(w):
    r=random.random(); acc=0
    for k,p in w:
        acc+=p
        if r<=acc: return k
    return w[-1][0]

# active (live) sessions
active=[]
for i in range(3):
    tid=random.choice(tables)
    cid=random.choice(CUSTOMER_IDS)
    st=now-dt.timedelta(minutes=random.randint(40,160))
    hours=(now-st).seconds/3600
    rt='hourly'; rate=rates[tid]
    amount=round(hours*rate,2)
    active.append((None,tid,cid,None,None,"Member %d"%cid,random.choice([2,3,4]),st,None,st+dt.timedelta(hours=random.randint(1,3)),
                   None,0,rt,'timer',rate,rate,0,0,amount,'unpaid',None,0,0.0,None,'active',random.choice([2,3,4]),
                   st.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
ins('sessions',["id","table_id","customer_id","player_winner","player_loser","client_name","players_count","start_time","end_time","expected_end_time","paused_at","paused_total_sec","rate_type","charge_type","fixed_amount","rate","discount","extra_charges","amount","payment_status","payment_method","is_loan","loan_amount","notes","status","staff_id","created_at","updated_at"],active)

# completed sessions over 60 days
sess_rows=[]; pay_rows=[]; sid=1000
for d in range(60):
    nd=int(random.triangular(2,7,4))
    day=now-dt.timedelta(days=d)
    for _ in range(nd):
        tid=random.choice(tables); cid=random.choice(CUSTOMER_IDS)
        h=random.choice([i for i in range(12,24)]+[i for i in range(0,2)])
        m=random.randint(0,55)
        dur=round(random.uniform(0.75,5.0),2)
        st=day.replace(hour=h,minute=m,second=random.randint(0,59)) if h<24 else day.replace(day=day.day-1,hour=0,minute=m)
        if h>=24: st=st+dt.timedelta(days=0)
        en=st+dt.timedelta(hours=dur)
        rt=pick(rate_type_w); rate=rates[tid]
        if rt=='vip' and tid not in (5,6,7): rt='hourly'
        disc=round(random.uniform(0,0.10),2) if random.random()<.25 else 0.0
        extra=round(random.uniform(0,200),0) if random.random()<.12 else 0.0
        amount=round(dur*rate*(1-disc)+extra,2)
        ps=random.random()
        paystat='paid' if ps<.75 else ('partial' if ps<.85 else 'unpaid')
        method=random.choice(['cash','jazzcash','bank_transfer','card'])
        staff=random.choice([2,3,4])
        sess_rows.append((sid,tid,cid,None,None,"Member %d"%cid,random.choice([2,3,4]),st,en,None,None,0,
                          rt,'timer',rate,rate,disc,extra,amount,paystat,None if paystat=='unpaid' else method,
                          0,0.0,None,'cancelled' if random.random()<.03 else 'completed',staff,
                          (st-dt.timedelta(minutes=5)).strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        if paystat=='paid':
            pay_rows.append((None,sid,None,cid,amount,method,'paid',random.choice(['TRX%06d'%random.randint(0,999999),None]),
                             None,(en+dt.timedelta(minutes=random.randint(1,20))).strftime('%Y-%m-%d %H:%M:%S'),staff,
                             (st-dt.timedelta(minutes=3)).strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        elif paystat=='partial':
            part=round(amount*random.uniform(.4,.7),2)
            pay_rows.append((None,sid,None,cid,part,method,'partial',None,None,(en+dt.timedelta(minutes=random.randint(1,15))).strftime('%Y-%m-%d %H:%M:%S'),staff,
                             (st-dt.timedelta(minutes=3)).strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        sid+=1
ins('sessions',["id","table_id","customer_id","player_winner","player_loser","client_name","players_count","start_time","end_time","expected_end_time","paused_at","paused_total_sec","rate_type","charge_type","fixed_amount","rate","discount","extra_charges","amount","payment_status","payment_method","is_loan","loan_amount","notes","status","staff_id","created_at","updated_at"],sess_rows)
ins('payments',["id","session_id","booking_id","customer_id","amount","method","status","transaction_ref","notes","paid_at","accepted_by","created_at","updated_at"],pay_rows)

# expenses
ex=[]
for d in range(60):
    if random.random()<.5:
        day=now-dt.timedelta(days=d)
        cat=random.choice(['Electricity','Labour','Rent','Snacks & Tea','Repairs'])
        ex.append((None,cat,round(random.uniform(300,15000),2),day.strftime('%Y-%m-%d'),'Cash',None,None,None,'approved',random.choice([2,3,4]),None,random.choice([2,3,4]),
                   day.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
ins('expenses',["id","category","amount","expense_date","paid_by","vendor","description","receipt_path","status","approved_by","notes","created_by","created_at","updated_at"],ex)

# bookings + deposits
bk=[];bkpay=[];bid=100
for d in range(30,-2,-1):
    if random.random()<.5:
        day=now-dt.timedelta(days=d); tid=random.choice(tables); cid=random.choice(CUSTOMER_IDS)
        h=random.choice([13,14,15,16,17,18,19,20,21,22]); st=day.replace(hour=h,minute=random.choice([0,30]))
        en=st+dt.timedelta(hours=random.choice([1,2,3]))
        status=random.choice(['confirmed','confirmed','completed','cancelled','arrived'])
        bamount=random.choice([300,500,500,1000]) if status!='cancelled' else 0
        bk.append((bid,tid,cid,"Member %d"%cid,"+923000000000",st.strftime('%Y-%m-%d'),st.strftime('%H:%M:%S'),en.strftime('%H:%M:%S'),
                   random.choice([2,3,4]),bamount,None if status=='cancelled' else random.choice(['cash','jazzcash']),status,
                   None if (day>now) else now.strftime('%Y-%m-%d %H:%M:%S'),None,None,random.choice([2,3,4]),now.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        if bamount and status!='cancelled':
            bkpay.append((None,None,bid,cid,bamount,random.choice(['cash','jazzcash']),'paid',None,None,
                          st.strftime('%Y-%m-%d %H:%M:%S'),random.choice([2,3,4]),now.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        bid+=1
ins('bookings',["id","table_id","customer_id","customer_name","customer_phone","booking_date","start_time","end_time","players_count","amount","payment_method","status","reminder_sent_at","player_winner","player_loser","created_by","created_at","updated_at"],bk)
ins('payments',["id","session_id","booking_id","customer_id","amount","method","status","transaction_ref","notes","paid_at","accepted_by","created_at","updated_at"],bkpay)

# tournament (completed) 8 players 7 matches
t_id=1
tour=(t_id,"Monthly Knockout — Ramazan Open",'single_elimination',200,"Winner Cup + Rs 5,000",3,
      (now-dt.timedelta(days=25)).strftime('%Y-%m-%d'),(now-dt.timedelta(days=12)).strftime('%Y-%m-%d'),
      'completed',None,None,random.choice([2,3]),now.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S'))
ins('tournaments',["id","name","type","entry_fee","prize_details","best_of","start_date","end_date","status","champion_id","notes","created_by","created_at","updated_at"],[tour])
pl=[]; champion=random.randint(1,8)
pids=random.sample(CUSTOMER_IDS,8)
for s in range(1,9):
    pl.append((None,t_id,pids[s-1],"Player %d"%s,"+92300%d"%random.randint(1000000,9999999),s,'champion' if s==champion else 'active',(now-dt.timedelta(days=26)).strftime('%Y-%m-%d %H:%M:%S')))
ins('tournament_players',["id","tournament_id","customer_id","name","phone","seed","status","registered_at"],pl)
# bracket: round1 seeds 1v8,2v7,3v6,4v5 ; round2; final
r1=[(1,8),(2,7),(3,6),(4,5)]; m_no=1; mrows=[]
for tdays,rd,pairs in [(25,1,r1),(18,2,[(1,2),(3,4)]),(12,3,[(1,2)])]:
    for (a,b) in pairs:
        sa,sb=random.choice([(3,0),(2,1)]); winner=a if sa>sb else b
        mrows.append((None,t_id,rd,m_no,random.choice(tables),
                      None,None,sa,sb,winner,'completed',
                      (now-dt.timedelta(days=tdays)).strftime('%Y-%m-%d %H:%M:%S'),None,now.strftime('%Y-%m-%d %H:%M:%S'),now.strftime('%Y-%m-%d %H:%M:%S')))
        m_no+=1
ins('tournament_matches',["id","tournament_id","round_num","match_no","table_id","player_home_id","player_away_id","score_home","score_away","winner_id","status","scheduled_at","notes","created_at","updated_at"],mrows)

open('/tmp/demo_extras.sql','w').write("\n".join(out))
print("wrote /tmp/demo_extras.sql, sessions:",len(sess_rows),"payments:",len(pay_rows),"expenses:",len(ex),"bookings:",len(bk))
