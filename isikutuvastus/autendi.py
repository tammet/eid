#!/usr/bin/python
# -*- coding: utf-8 -*-

import os, re

def get_user():
  """Get user information obtained by Apache from the Estonian ID card.
     Returns list [last_name,first_name,person_code] or None if fails. 
  """
  # get relevant environment vars set by Apache
  # SSL_CLIENT_S_DN example:
  # /C=EE/O=ESTEID/OU=authentication/CN=SMITH,JOHN,37504170511/
  # SN=SMITH/GN=JOHN/serialNumber=37504170511
  ident=os.getenv('SSL_CLIENT_S_DN')
  verify=os.getenv('SSL_CLIENT_VERIFY')
  # check and parse the values
  if not ident or verify!="SUCCESS": return None
  ident=str2utf8(ident) # old cards use UCS-2, new cards use UTF-8
  if not ident.startswith("/C=EE/O=ESTEID"): return None
  ps=ident.find("/SN=")
  pg=ident.find("/GN=")
  pc=ident.find("/serialNumber=")
  if ps<=0 or ps<=0 or pc<=0: return None
  res=[ident[ps+4:pg], ident[pg+4:pc], ident[pc+14:]]
  return res

def str2utf8(s):
  """Convert names from UCS-2/UTF-16 to UTF-8.""" 
  try:
    s=re.sub("/\\\\x([0-9ABCDEF]{1,2})/e", "chr(hexdec('\\1'))", s);
    if s.find("\x00")>0: 
      x=s.decode(encoding="utf-16") 
      s=x.encode(encoding="utf-8")    
      return s
    return s
  except:
    return "" # conversion failed    

# Actual script to run  

print("Content-type: text/html; charset=utf-8\n\n")
user=get_user()
if not user: 
  print("Authentication failed.")
else:
  print("Last name: "+user[0]+"<br>")
  print("First name: "+user[1]+"<br>")
  print("Person code: "+user[2]+"<br>")
