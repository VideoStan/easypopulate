License: GPL (please see license.txt)
-------------------------------------------------

READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!

OVERVIEW
---------

Easy Populate allows you to add/update products from a delimited
text file, which can be edited in Excel, or OpenOffice Calc. Please use Excel
with care as most versions have trouble with files with lots of columns/rows.
Please make sure to backup your products before trying it.

Installing
----------
1) The temp folder is configured to reside in your store directory. If you store is not in the
   site root (eg. /mystore/) then the temp directory must also go in this directory.
2) If you want to rename the uploads (temp) folder, ensure that you configure Easy Populate to
   reflect this change after installation
3) Upload all files in their respective directories.
4) Go to Admin -> Tools -> Easy Populate. If prompted to install Easy Populate, click on the
   link provided. Otherwise, type in your browser address bar ?epinstaller=install after /easypopulate.php
   (eg. YOUR_ADMIN/easypopulate.php?epinstaller=install). If you wish to remove and re-install the default
   config settings, type ?epinstaller=remove after /easypopulate.php
   (eg. YOUR_ADMIN/easypopulate.php?epinstaller=remove) and begin this step again.
5) Go to Admin -> Configuration -> Easy Populate, and configure the upload directory if you have
   changed it from default. Also, ensure that the correct date format is set for your upload files.

Upgrading
---------
If you are upgrading from 1.2.5.5 (Update added on Jan 12 2009):

You must point your browser to:
YOUR_ADMIN/easypopulate.php?epinstaller=remove
and then:
YOUR_ADMIN/easypopulate.php?epinstaller=install
This will prompt for the new configuration values to be reinstalled.
############

IMPORTANT!!!
-------------

** your temp folder must have owner permissions to read/write/execute (chmod 700 or chmod 777) for EP to work.
(unless you have a windows server - ask your host to adjust permissions on this folder for the IIS web server)

** It is recommended that you download a file first and use this as a template for your uploads (install should do this for you..)


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

* Feasibility of addition to smart-tags for creating <ul> lists based on occurance of blank lines & bullets, asterisks etc.
* Listing of files in /temp/ (via iframe?) with options to upload, delete, refresh list.
	- function zen_remove($source) - removes file/directory
* add button and function for complete SQL backup. Add config option to automatically backup to file all products prior to any uploads?
* Add buttons/display to manage backup SQL files (delete, restore etc..) displaying date etc.
	- function zen_remove($source) - removes file/directory
* Additional tools for locating directories/manufacturers etc without any products, giving user option to delete.