License: GPL (please see license.txt)
-------------------------------------------------

READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!

OVERVIEW
---------

Easy Populate allows you to add/update products from a tab or comma delimited
text file, which can be edited in Excel, or OpenOffice Calc. Please use Excel
with care as most versions have trouble with files with lots of columns/rows.
Please make sure to backup your products before trying it.

USAGE
---------

** Downloading **

Once you have installed & configured Easy Populate, go to
Tools -> "Easy Populate" in Zen Cart admin

There are multiple file types to choose to either download, or dump into your
/temp/ folder on your website. All of the files will download data for all of
your store's products. Depending on what you wish to update, choose the most
appropriate file.

Download file types:
1) Complete - All product information, including Specials. Attributes are
currently only available as separate download while I renovate the HTML area
of Easy Populate. HINT: USE THIS FILE FOR YOUR TEMPLATE FOR ADDING NEW PRODUCTS
- Download it and remove all but one product for your reference when adding new
products

2) Model/Price/Qty - For updating pricing and/or Qty only, including Specials!

3) Model/Category - For updating/adding product categories

4) Froogle - for Froogle feeds

5) Model/Attributes - Just the attributes & options


** Uploading **

Either transfer your file to your /temp/ directory (best for large files), type
in the name of the file and choose "Import from Temp Dir (temp/)"

or

Browse to your file on your harddrive and choose "Upload EP File"

Things you should know about uploads:
 * Each time you update a product (any product in an upload) the last_updated
field is set to now.
 * When deleting products using v_status = 9, All products with the exact model
number of the flagged product are deleted
 * Changing the category of a product will create a linked product in the new
category (same product, just a navigation link)
 * NOTE: THE PRODUCT MODEL IS THE INDEX! All product updates are done using the
products model as the index.
 * Any fields left blank (NULL) will not always retain the current data in your
store. Best to delete unneeded columns, and use original store data as updates
template
 * The categories are the category levels, and not multiple categories:
Category1 -> Category2 (sub-cat) -> Category3 (sub-sub-cat) etc..
 * Specials - To delete a special, make the price zero (0) in your upload file.
To add, simply put a price in. All blank specials price fields are ignored by
Easy Populate.

TIMEOUTS: You may experience timeouts when adding/updating large numbers of
products. If you have a large file that times out, you can use the
"Split EP File" option. This will create multiple files containing a maximum of
300 records (default - you can change this in Admin). Thus, 1000 products will
produce 4 files. These files are created in your /temp/ folder, from where you
can upload each in turn after they have been split. If 300 is too many or too
few for your circumstances, adjust it in
Admin -> Configuration -> "Easy Populate".


CONFIGURATION
-------------

See Admin -> Configuration -> "Easy Populate".

SUPPORT
-------

You can find some useful information here:
http://www.zen-cart.com/modules/ipb/index.php?showtopic=31073


HISTORY
--------

You can find a list of releases (and contributors) here: history.txt


EXAMPLE OUTPUT FILES
-------------------

See the /temp/ folder for examples


KNOWN ISSUES
-------------
* Large numbers of Options & Attributes can cause column number to exceed
display maximums in file editors (Excel, etc.)
* CHMOD permissions on your temp folder of 700 may not work, depending on
the config of your server. Make the permissions 777 if this is the case,
and ensure that you have the index.html file in the directory if you wish
to prevent browsing of your upload files.

TO DO
-----

Important:
* renovate html & finalise language support
* add help links for each error generated (include 'ep_error.php')
* Choose between create linked product on new category (default behaviour) or move to new category
* Add dates to specials summary. Put summary data (prices) in same order as in downloads file
* investigate gzip compression for uploads and downloads
* optimisation of code to reduce sql calls?
* update this document with all the new information

Can wait:
* Dual Price Mod support? Maybe I should just write a real-time discount by user-group mod..
* if upload file not exist, error.
* Revamp downloads layout for modular construction - makes little sense to have multiple configs
* generate an optional "exceptions" dump on update - users that update their site from supplier list could use this to hide/delete products removed from supplier's list.
	# or maybe menu of option for what to do with these - delete, make inactive, etc.
* Set file suffix in config (Eg .tab) - easy
* Feasibility of addition to smart-tags for creating <ul> lists based on occurance of blank lines & bullets, asterisks etc.
* Listing of files in /temp/ (via iframe?) with options to upload, delete, refresh list.
	- function zen_remove($source) - removes file/directory
* add button and function for complete SQL backup. Add config option to automatically backup to file all products prior to any uploads?
* Add buttons/display to manage backup SQL files (delete, restore etc..) displaying date etc.
	- function zen_remove($source) - removes file/directory
* Additional tools for locating directories/manufacturers etc without any products, giving user option to delete.
