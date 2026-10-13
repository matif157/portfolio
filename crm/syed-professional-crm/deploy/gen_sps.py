import random, datetime as dt
random.seed(42); now=dt.datetime.now()
out=[]; O=[]
def ins(t, cols, rows):
    if not rows: return
    out.append(f"INSERT INTO `{t}` ({','.join(cols)}) VALUES")
    def fmt(v):
        if v is None: return 'NULL'
        if isinstance(v,(int,float)): return str(v)
        if isinstance(v,(dt.datetime,dt.date)): return "'%s'"%v.strftime('%Y-%m-%d %H:%M:%S' if isinstance(v,dt.datetime) else '%Y-%m-%d')
        return "'%s'"%str(v).replace("'","''")
    out.append(",\n".join("("+",".join(fmt(x) for x in r)+")" for r in rows)+";")

firsts=['Ahmed','Muhammad','Ali','Bilal','Danish','Ehsan','Farhan','Hassan','Imran','Junaid','Kamran','Usman','Tariq','Zubair','Saad','Waqas','Asad','Noman','Rizwan','Shahbaz']
lasts=['Khan','Hussain','Shah','Malik','Qureshi','Butt','Cheema','Gujjar','Siddiqui','Rana','Abbasi','Farooq','Javed','Mehmood','Raza','Sajjad','Tariq','Yusuf']
nat=['Pakistan','United Kingdom','United States','Germany','UAE','Australia','Canada']
serv=[('IMMIGRATION','Immigration Services','Immigration','Client immigration & visa workflows.'),
      ('DEBT_COLLECTION','Debt Collection','Collection','Out-of-court & legal debt recovery.'),
      ('LEGAL_NOTICE','Legal Notice Drafting','Legal','Demand and warning notices.'),
      ('DOCS_APOSTILLE','Document Attestation','Documentation','Attestation, apostille, translation.'),
      ('VISA_SPONSOR','Visa Sponsorship','Immigration','Employer visa sponsorship.'),
      ('PERMANENT_RES','Permanent Residency','Immigration','PR application & points review.')]
reqs={'IMMIGRATION':['Passport bio page','CNIC copy','Proof of address','Bank statement 6m','Photographs'],
      'DEBT_COLLECTION':['Debt agreement','Promissory note','Party CNIC','Proof of demand','Bank statement'],
      'LEGAL_NOTICE':['Party details','Claim amount','Supporting docs','Address of respondent'],
      'DOCS_APOSTILLE':['Original document','Copies','Notary attestation'],
      'VISA_SPONSOR':['Company registration','Offer letter','Passport','Financial proof'],
      'PERMANENT_RES':['Passport','CV','Degree certs','Work references','Bank statements']}
m=[]; nowt=str(now)

# organizations
orgs=[]; orgname=[
 lambda i:"Al-Rahim Immigration Consultants",
 lambda i:"Gulberg Business Partners",
 lambda i:"Channar Textiles (Pvt) Ltd",
 lambda i:"Sahiwal Traders Co."]
for i in range(1,11):
    orgs.append((i,"Organization %d"%i,"org%d@demo.local"%i,"+92300%d"%random.randint(1000000,9999999),nowt,nowt))
# clients
clients=[]
for i in range(1,50):
    full="%s %s"%(random.choice(firsts),random.choice(lasts))
    clients.append((i,random.choice(list(range(1,11))) if random.random()<.4 else None,None,full,"%s.%d@demo.local"%('c',i),
                    "+92300%d"%random.randint(1000000,9999999),random.choice(nat),random.choice(['active','active','active','inactive']),
                    nowt,nowt))
# contacts
contacts=[]
for i in range(1,56):
    contacts.append((i,random.choice(list(range(1,50))),None,"%s %s Contact"%(random.choice(firsts),random.choice(lasts)),
                     "contact%d@demo.local"%i,"+92300%d"%random.randint(1000000,9999999),random.choice(['primary','secondary','guarantor']),nowt))
# services + requirements
sids=[]
for i,(code,name,cat,desc) in enumerate(serv,start=1):
    sids.append(i); m.append((i,code,name,cat,desc,'active',nowt,nowt))
sidmap={s[0]:i for i,s in enumerate(serv,start=1)}
req_rows=[]; rid=1
for code,opts in reqs.items():
    sid=sidmap[code]
    for j,r in enumerate(opts,start=1):
        req_rows.append((rid,sid,'req_%d_%d'%(sid,j),r,'document',1 if j<4 else 0,None,j)); rid+=1
# users
admin=(1,'Syed Professional Services (Demo)','admin@demo.local','$HASH','Admin','active',nowt,nowt)
staff=[(2,'Senior Consultant','staff2@demo.local','$HASH','Staff','active',nowt,nowt),
       (3,'Case Manager','staff3@demo.local','$HASH','Staff','active',nowt,nowt),
       (4,'Accounts Officer','staff4@demo.local','$HASH','User','active',nowt,nowt)]
# cases
stages=['intake','documents','review','pending','sent','completed']; statuses=['open','open','in_progress','closed']
cases=[]
for i in range(1,45):
    cli=random.choice(list(range(1,50))); sv=random.randint(1,6); n=random.randint(20000,99999)
    created=now-dt.timedelta(days=random.randint(2,75))
    cases.append((i,cli,sv,"CASE-%06d"%n,random.choice(stages),random.choice(statuses),random.choice(['low','normal','normal','high']),
                  random.choice([1,2,3,None]), (created+dt.timedelta(days=random.randint(5,80))).strftime('%Y-%m-%d'),
                  created.strftime('%Y-%m-%d %H:%M:%S'),nowt))
# case_requirements
creq=[]; crid=0
for c in cases:
    cid=c[0]; sv=c[2]
    for reqid in [x for x in req_rows if x[1]==sv]:
        crid+=1
        st=random.choice(['missing','uploaded','verified','verified'])
        creq.append((crid,cid,reqid[0],st,'sample path' if st!='missing' else None,
                     (now-dt.timedelta(days=random.randint(1,30))).strftime('%Y-%m-%d %H:%M:%S') if st=='verified' else None))
# projects
projects=[]
for i in range(1,22):
    casesel=random.choice(cases); cid=casesel[0]
    start=now-dt.timedelta(days=random.randint(2,60))
    projects.append((i,cid,None,"Project %02d"%i,random.choice(['active','active','on_hold','completed']),
                     random.randint(15,100),random.choice([2,3,1]),start.strftime('%Y-%m-%d'),
                     (start+dt.timedelta(days=random.randint(15,120))).strftime('%Y-%m-%d'),start.strftime('%Y-%m-%d %H:%M:%S'),nowt))
# tasks
tasks=[]
for i in range(1,120):
    c=random.choice(cases); done=random.random()<.55
    dued=now+dt.timedelta(days=random.randint(-30,25))
    tasks.append((i,random.choice(list(range(1,50))),c[0],random.choice(list(range(1,22))) if random.random()<.6 else None,
                  random.choice([1,2,3,None]),"Task %02d for client workflow"%i,"Follow up and record outcome." if random.random()<.5 else None,
                  'completed' if done else random.choice(['pending','in_progress','pending']),random.choice(['high','normal','low']),
                  dued.strftime('%Y-%m-%d %H:%M:%S'), (dued-dt.timedelta(hours=random.randint(1,20))).strftime('%Y-%m-%d %H:%M:%S') if done else None,
                  'auto' if random.random()<.3 else None, (dued-dt.timedelta(days=random.randint(2,25))).strftime('%Y-%m-%d %H:%M:%S'),nowt))
# invoices
inv=[]
for i in range(1,40):
    cid=random.choice(list(range(1,50))); subt=round(random.uniform(25000,250000),2)
    tax=round(subt*.175,2); total=round(subt+tax,2); st=random.choice(['draft','sent','partial','paid','paid','overdue'])
    paid=total if st=='paid' else (round(total*random.uniform(.4,.6),2) if st=='partial' else 0)
    issued=now-dt.timedelta(days=random.randint(5,70))
    inv.append((i,cid,"INV-%04d"%i,st,'PKR',subt,tax,total,paid,round(total-paid,2),
                issued.strftime('%Y-%m-%d'),(issued+dt.timedelta(days=random.randint(14,45))).strftime('%Y-%m-%d'),None,
                issued.strftime('%Y-%m-%d %H:%M:%S'),nowt))
# payments
pay=[]
for i in range(1,34):
    inbox=random.choice(inv)
    if inbox[3]=='paid':
        amt=inbox[8]
    elif inbox[3]=='partial':
        amt=inbox[8]
    else: continue
    pay.append((i,inbox[0],inbox[1],"PAY-%05d"%i,random.choice(['Bank Transfer','JazzCash','Cash']),amt,'PKR','received',
                (now-dt.timedelta(days=random.randint(1,50))).strftime('%Y-%m-%d %H:%M:%S'),None))
# comments
com=[]; cmt_texts=["Followed up with client.","Documents verified.","Call scheduled.","Payment reminder sent.","Awaiting translation.","Escalated to manager.","Partial payment received."]
for i in range(1,50):
    c=random.choice(cases)
    com.append((i,random.choice(list(range(1,50))),c[0],random.choice(list(range(1,22))),None,random.choice([None,1,2,3]),
                random.choice(cmt_texts),'crm',None,(now-dt.timedelta(days=random.randint(1,60))).strftime('%Y-%m-%d %H:%M:%S')))
# notifications
noti=[]
for i in range(1,30):
    noti.append((i,1,random.choice(list(range(1,50))),random.choice(['case_update','payment','reminder','document']),
                 "Notification %d"%i,"A demo notification body.",random.choice([None,now.strftime('%Y-%m-%d %H:%M:%S')]),
                 (now-dt.timedelta(days=random.randint(0,20))).strftime('%Y-%m-%d %H:%M:%S')))
# workflow_runs
wr=[]
for i in range(1,26):
    c=random.choice(cases)
    wr.append((i,c[0],random.choice(stages),random.choice(stages),'{}',round(random.uniform(.7,.99),4),0,'approved' if random.random()<.7 else 'auto',
               (now-dt.timedelta(days=random.randint(1,40))).strftime('%Y-%m-%d %H:%M:%S'),nowt))
# approvals
ap=[]
for i in range(1,20):
    a=random.choice(cases)
    ap.append((i,a[0],None,random.choice([2,3]),random.choice([1,2,3]),'release','approved' if random.random()<.7 else 'pending','Standard approval.', 
               (now-dt.timedelta(days=random.randint(1,30))).strftime('%Y-%m-%d %H:%M:%S'),
               random.choice([None,nowt])))
# audit_logs
aud=[]
for i in range(1,60):
    ent=random.choice(['service_cases','invoices','clients','tasks','documents'])
    aud.append((i,random.choice([1,2,3]),random.choice(['Created','Updated','Viewed','Exported','Logged in']),ent,random.randint(1,60),None,
                (now-dt.timedelta(days=random.randint(0,40))).strftime('%Y-%m-%d %H:%M:%S')))

ins('users',["id","name","email","password_hash","role","status","created_at","updated_at"],[admin]+staff)
ins('organizations',["id","name","email","phone","created_at","updated_at"],orgs)
ins('clients',["id","organization_id","external_id","full_name","email","phone","nationality","status","created_at","updated_at"],clients)
ins('contacts',["id","client_id","organization_id","name","email","phone","type","created_at"],contacts)
ins('services',["id","code","name","category","description","status","created_at","updated_at"],m)
ins('service_requirements',["id","service_id","code","label","type","required","rules","sort_order"],req_rows)
ins('service_cases',["id","client_id","service_id","case_number","stage","status","priority","assigned_to","due_date","created_at","updated_at"],cases)
ins('case_requirements',["id","case_id","requirement_id","status","value_text","verified_at"],creq)
ins('projects',["id","client_id","case_id","name","status","progress","manager_id","start_date","due_date","created_at","updated_at"],projects)
ins('tasks',["id","client_id","case_id","project_id","assigned_to","title","description","status","priority","due_at","completed_at","automation_source","created_at","updated_at"],tasks)
ins('invoices',["id","client_id","invoice_number","status","currency","subtotal","tax","total","paid","balance","issued_at","due_at","metadata","created_at","updated_at"],inv)
ins('payments',["id","invoice_id","client_id","reference","method","amount","currency","status","received_at","metadata"],pay)
ins('comments',["id","client_id","case_id","project_id","invoice_id","user_id","body","source","external_id","created_at"],com)
ins('notifications',["id","user_id","client_id","type","title","body","read_at","created_at"],noti)
ins('workflow_runs',["id","case_id","from_stage","to_stage","decision","confidence","approval_required","status","created_at","completed_at"],wr)
ins('approvals',["id","case_id","task_id","requested_by","approved_by","action","status","reason","created_at","decided_at"],ap)
ins('audit_logs',["id","user_id","action","entity","entity_id","metadata","created_at"],aud)

open('/tmp/sps_extras.sql','w').write("\n".join(out))
tot=sum(len(x) for x in [clients,contacts,cases,creq,projects,tasks,inv,pay,com,noti,wr,ap,aud])
print("wrote /tmp/sps_extras.sql rows(incl users/svc/org):",tot+16+len(req_rows))
