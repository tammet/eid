#!/usr/bin/ruby

require 'iconv'

# Get user information obtained by Apache from the Estonian ID card.
# Return list [last_name,first_name,person_code] or nil if fails. 

def get_user
  # Get user information obtained by Apache from the Estonian ID card.
  #  Returns list [last_name,first_name,person_code] or nil if fails. 
  # get relevant environment vars set by Apache
  # SSL_CLIENT_S_DN example:
  # /C=EE/O=ESTEID/OU=authentication/CN=SMITH,JOHN,37504170511/
  # SN=SMITH/GN=JOHN/serialNumber=37504170511
  ident=ENV["SSL_CLIENT_S_DN"]
  verify=ENV["SSL_CLIENT_VERIFY"];
  # check and parse the values
  if !ident or verify!="SUCCESS"
    return nil
  end    
  ident=str2utf8(ident) # old cards use UCS-2, new cards use UTF-8
  if ident.index("/C=EE/O=ESTEID")!=0
    return nil
  end  
  ps=ident.index("/SN=")
  pg=ident.index("/GN=")
  pc=ident.index("/serialNumber=")
  if !ps or !pg or !pc
    return nil
  end  
  res=[ident[ps+4..pg-1], ident[pg+4..pc-1], ident[pc+14..-1]]
  return res
end  

# Convert names from UCS-2/UTF-16 to UTF-8.

def str2utf8(s)
  begin
    s=s.gsub("/\\\\x([0-9ABCDEF]{1,2})/e", "chr(hexdec('\\1'))")
    if s.index("\x00")
      conv=Iconv.new("UTF-8//IGNORE","UTF-16")
      s=conv.iconv(s) 
      return s
    end  
    return s
  rescue
    return "" # conversion failed 
  end  
end    

# Actual script to run

puts "Content-type: text/html; charset=utf-8\n\n"
user=get_user()
if !user 
  puts "Authentication failed."
else
  puts "Last name: "+user[0]+"<br>"
  puts "First name: "+user[1]+"<br>"
  puts "Person code: "+user[2]+"<br>"
end  