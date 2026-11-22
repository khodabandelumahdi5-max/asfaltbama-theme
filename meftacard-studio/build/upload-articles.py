import re,glob,json,os,sys,urllib.parse,subprocess
import markdown
API='https://meftacard.com/wp-json'
def req(method,path,data=None):
    cmd=['curl','-s','-X',method,API+path,'-H','Content-Type: application/json']
    if data is not None: cmd+=['--data-binary','@-']
    r=subprocess.run(cmd,input=json.dumps(data) if data is not None else None,capture_output=True,text=True)
    try: return json.loads(r.stdout)
    except Exception: print('RAW',r.stdout[:300]); raise
def category(name,slug):
    ex=req('GET','/wp/v2/categories?slug='+slug)
    if ex: return ex[0]['id']
    return req('POST','/wp/v2/categories',{'name':name,'slug':slug})['id']
cats={'en':category('Web Design, SEO & AI Agents','web-design-seo-ai-agents'),
      'ar':category('تصميم المواقع والسيو والذكاء الاصطناعي','web-design-seo-ai-agents-ar')}
print('cats',cats)
out={}
for f in sorted(glob.glob('*/*.md')):
    s=open(f).read(); _,fm,body=s.split('---',2)
    meta={}
    for line in fm.strip().splitlines():
        k,v=line.split(':',1); v=v.strip()
        meta[k]=json.loads(v) if v.startswith(('"','[')) else v
    lang=meta['lang']
    body=re.sub(r'^\s*# .*\n','',body,count=1)   # WP title is the H1
    title=meta['title']
    html=markdown.markdown(body,extensions=['tables','fenced_code','sane_lists'])
    html=html.replace('<table>','<figure class="wp-block-table"><table>').replace('</table>','</table></figure>')
    html=f'<div lang="{lang}" dir="{"rtl" if lang=="ar" else "ltr"}">\n{html}\n</div>'
    slug=meta['slug']
    ex=req('GET',f'/wp/v2/posts?slug={slug}&status=draft,publish,pending,private&context=edit&_fields=id')
    data={'title':title,'slug':slug,'content':html,'status':'draft','categories':[cats[lang]],
          'excerpt':meta['meta_description']}
    p=req('POST',f"/wp/v2/posts/{ex[0]['id']}" if ex else '/wp/v2/posts',data)
    pid=p.get('id'); assert pid,p
    kw=','.join([meta['focus_keyword']]+[k for k in meta.get('secondary_keywords',[]) if k!=meta['focus_keyword']][:4])
    rm=req('POST','/rankmath/v1/updateMeta',{'objectType':'post','objectID':pid,'meta':{
        'rank_math_title':meta['seo_title'],'rank_math_description':meta['meta_description'],'rank_math_focus_keyword':kw}})
    out[slug]={'id':pid,'lang':lang,'status':p.get('status'),'rankmath':rm if not isinstance(rm,dict) else list(rm)[:3]}
    print(f"{slug:45} id={pid} {p.get('status')} rm={str(rm)[:60]}")
json.dump(out,open(os.environ['SP']+'/posts.json','w'),indent=1)
