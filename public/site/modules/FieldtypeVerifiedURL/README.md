ProcessWire ProFields: Verified URL Fieldtype
=============================================

This is a commercial module that is part of the ProcessWire ProFields package, so
do not distribute this module anywhere other than the website(s) you have developed. 


What it does
------------
FieldtypeVerifiedURL is like the core FieldtypeURL module except that it and adds the 
ability to verify that the entered URL actually exists. It does this with an HTTP HEAD 
request. That HEAD request returns an HTTP status code, which reveals whether the URL 
is active and working, is producing a redirect, or is producing an error. 

The module remembers this status code and stores it with the URL, so that you can easily 
isolate working URLs from non-working URLs. This Fieldtype is particularly handy on sites 
that have to maintain a lot of URLs for one reason or another. 

In addition to initially verifying the status of a URL, the module goes back and 
re-verifies the URLs at set intervals. This ensures the long-term quality of the URLs that 
you store in your site's data. If an initially valid URL later becomes a 404 "not found", 
this module will find it for you automatically in the background. 


Requirements
------------
- ProcessWire core version 3.0.200 (or newer).
- Ability to make outbound http connections (uses ProcessWire’s WireHttp class)
  
 
How to install
--------------
1. Copy all the files in this directory to `/site/modules/FieldtypeVerifiedURL/`

2. In your admin, go to Modules > Refresh

3. Click the "Install" button next to FieldtypeVerifiedURL (on the “Site” tab). 


How to upgrade to a newer version
---------------------------------
1. Rename your `/site/modules/FieldtypeVerifiedURL/` directory to:
   `/site/modules/.FieldtypeVerifiedURL/` (with the period as indicated).
   
   This makes the directory hidden to ProcessWire and serves as your backup in case
   you need to restore it for any reason. If the directory already exists, you may want 
   to remove it first or, if you prefer, append a version number like: 
   
   `/site/modules/.FieldtypeVerifiedURL-3/`

2. Now create the directory `/site/modules/FieldtypeVerifiedURL/` and place all the files
   from the new version in that directory. 
   
3. In your ProcessWire admin, navigate to Modules > Refresh.    

4. Your version is now upgraded. Double check that everything works as you expect. 


How to create a verified URL field
----------------------------------
1. In your admin, go to Setup > Fields > Add New. 

2. Enter a field name (i.e. "some_url")  and label, and select 
   "ProFields: Verified URL" for the "Type". Save.

3. Click to the "Details" tab. There are several options to review and/or
   configure for Verified URLs. 
   
5. Save your field, and now go to edit the template you want to add this field
   on (Setup > Templates). Add the field you just created and Save. 

6. Now go and edit or create a page using the field to see the results. Likely
   the first thing you will want to do is enter a URL in your new field to
   test things out. 


How to access your field from the API
-------------------------------------
The string value of a Verified URL field is always the URL itself. However, 
a Verified URL is technically an object with a few other properties. Here are
a few examples of outputting these properties from a field named some_url: 
~~~~~
// output the URL
echo $page->some_url; // http://weekly.pw

// output the status code of the URL
echo $page->some_url->status; // 301

// output the status string of a URL
echo $page->some_url->statusStr; // 200 OK (3 hours ago)

// output the redirect URL (if 301 or 302 status)
echo $page->some_url->redirect; // https://weekly.pw

// output the <title> tag of the URL (if enabled in field settings)
echo $page->some_url->title; // ProcessWire Weekly
~~~~~  
You can also find pages by status. For instance, here is how we would find 
all pages having a some_url field that resulted in a 404 status:
~~~~~
// find all pages with 404s in some_url field
$items = $pages->find("some_url.status=404"); 
~~~~~


Support & upgrades
------------------
Please see the ProFields support board at <https://processwire.com/talk/>. If you
have purchased ProFields and don't have access to the support board, please 
send a PM to Ryan in the forum or email ryan@processwire.com. 

To install an upgrade you would typically just replace the old files 
with the new. However, there may be more to it, depending on the version.
Always follow any instructions provided with the upgrade version in
the support board. 


Terms and conditions
====================

FieldtypeVerifiedURL and VerifiedURL are part of the ProFields package of modules 
by Ryan Cramer Design, LLC.

You may not copy or distribute ProFields, except on site(s) you (the purchaser
of ProFields) have developed. It is okay to make copies for use on staging 
or development servers specific to the site you registered for. 

This service/software includes 1-year of support through the ProcessWire ProFields
Support forum and/or email. 

In no event shall Ryan Cramer Design, LLC or ProcessWire be liable for any special, 
indirect, consequential, exemplary, or incidental damages whatsoever, including, 
without limitation, damage for loss of business profits, business interruption, 
loss of business information, loss of goodwill, or other pecuniary loss whether 
based in contract, tort, negligence, strict liability, or otherwise, arising out of 
the use or inability to use ProcessWire ProFields, even if Ryan Cramer Design, LLC / 
ProcessWire has been advised of the possibility of such damages. 

ProFields is provided "as-is" without warranty of any kind, either expressed or 
implied, including, but not limited to, the implied warranties of merchantability and
fitness for a particular purpose. The entire risk as to the quality and performance
of the program is with you. Should the program prove defective, you assume the cost 
of all necessary servicing, repair or correction. If within 7 days of purchase, you 
may request a full refund. Should you run into any trouble with ProFields, please
email for support or visit the ProFields Support forum. 


---
Copyright 2023 by Ryan Cramer Design, LLC