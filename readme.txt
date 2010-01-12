-------------------------------------------------
Easy Populate v1.2.5.7
-------------------------------------------------
Last updated: October 29, 2009

-- fixed bug where the new added custom field section was not getting uninstalled from database when uninstallation was run. If you uninstalled and reinstalled the program would give a database "duplicate" error.
-- added "Download Model/Price/Qty/Last Modified/Status .csv file to edit" to download as I needed date modified to change when uploading so that an addon I use auto catches which item was edited inorder to change quantity on eBay.
-- version number was 1.2.5.6 but in easypopulate admin section 1.2.5.5 was still showing.

Mod looks great and works perfectly, I would like to thank the creator and all the people that have kept it up to date.

-------------------------------------------------
Easy Populate v1.2.5.6
-------------------------------------------------
Last updated: June 18, 2009

Add custom fields to configuration table. You may add a comma separated list of fields to automatically
be add to the import/export file. Under Configuration->Easy Populate->Custom Fields

Fields must already exist in PRODUCTS table.

To reinstall new configuration:
http://your-store.com/admin/easypopulate.php?langer=remove

Warning message will appear at top of window with a link to reinstall new configurations.

-------------------------------------------------
Easy Populate v1.2.5.5
-------------------------------------------------
Last updated: December 1, 2006
Author: see history.txt (has a long and bloody history...)
        
Maintainer:
Heavily modified, fixed, poked, edited, added-to and otherwise improved immensely by Nicholas Keown (langer@modhole.com)
If using this mod means you now spend more time with your family & friends, please consider donating to
paypal@portability.com.au to ensure this module is continually supported and improved :-)

License: GPL (please see license.txt)
-------------------------------------------------

READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!
READ THE INSTALL FILE!!!!!!!

CHANGELOG
---------
v 1.2.5.5 by Robert Shady (oeginc)
- fixed problem with salemaker not working on imported items.
- fixed CR/LF's in license.txt file to display properly under windows like the rest of the .txt files.


v 1.2.5.4
- fixed froogle error (thanks to Baelzebub)
- Images changes
	* default product image is now zencart default.
	* Removed default manufacturer image.. you either have a manufacturer image, or none.
	* Removed default category image - set to null for new categories (zencart default)
- removed requirement for Froogle configuration. Now automatically done. Removed unwanted Froogle columns.
- migrated config to admin area of Zen Cart
- Froogle modification - now defaults to largest image avilable. No url is provided if no image available, or default blank image used.
- improved file permission check/adjust to cater for servers where chmod 700 is not enough


OVERVIEW
---------

Easy Populate allows you to add/update products from a tab delimited text file, which can be edited in M$ Excel, or better still (because you will overload Excel..) OpenOffice. Up to and including v2.75 it has been extremely dodgey to use. I think this version is fairly bug free, but use it at your own risk! BACKUP before using it!!! You have been warned!!!


USAGE
-----

** General **

Easy Populate will now warn you if you have any partially deleted products (data fragments) and provides you with the option to delete these. This will fix some unwanted behaviour that occurs from messed data. It only deletes product information related to products that do not exist in table "products". This situation results from previous buggy Easy Populate versions, as well as other upload tools, and possibly any product add/delete actions that do not go as they should.

Debug logging is now automatic for certain errors. You will be alerted of these unforeseen errors, and can use the debug file to assist in overcoming these.

** Downloading **

Once you have installed & configured Easy Populate, go to Tools -> "Easy Populate" in Zen Cart admin

There are 5 file types to choose to either download, or dump into your /temp/ folder on your website. All of the files will download data for all of your store's products. Depending on what you wish to update, choose the most appropriate file.

Download file types:
1) Complete - All product information, including Specials. Attributes are currently only available as separate download while I renovate the HTML area of Easy Populate. HINT: USE THIS FILE FOR YOUR TEMPLATE FOR ADDING NEW PRODUCTS - Download it and remove all but one product for your reference when adding new products

2) Model/Price/Qty - For updating pricing and/or Qty only, including Specials!

3) Model/Category - For updating/adding product categories

4) Froogle - for Froogle feeds

5) Model/Attributes - Just the attributes & options


** Uploading **

Either transfer your file to your /temp/ directory (best for large files), type in the name of the file and choose "Import from Temp Dir (temp/)"

or

Browse to your file on your harddrive and choose "Upload EP File"

Things you should know about uploads:
 # Each time you update a product (any product in an upload) the last_updated field is set to now.
 # When deleting products using v_status = 9, All products with the exact model number of the flagged product are deleted (no duplicate product model names in EP!)
 # Changing the category of a product will create a linked product in the new category (same product, just a navigation link)
 # NOTE: THE PRODUCT MODEL IS THE INDEX! All product updates are done using the products model as the index.
 # Any fields left blank (NULL) will not always retain the current data in your store. Best to delete unneeded columns, and use original store data as updates template
 # The categories are the category levels, and not multiple categories: Category1 -> Category2 (sub-cat) -> Category3 (sub-sub-cat) etc..
 # Specials - To delete a special, make the price zero (0) in your upload file. To add, simply put a price in. All blank specials price fields are ignored by Easy Populate.

TIMEOUTS: You may experience timeouts when adding/updating large numbers of products. If you have a large file that times out, you can use the "Split EP File" option. This will create multiple files containing a maximum of 300 records (default - you can change this in Admin). Thus, 1000 products will produce 4 files. These files are created in your /temp/ folder, from where you can upload each in turn after they have been split. If 300 is too many or too few for your circumstances, adjust it in Admin -> Configuration -> "Easy Populate".


CONFIGURATION
-------------

See Admin -> Configuration -> "Easy Populate".


DOCUMENTATION
-------------

There is no documentation for Easy Popluate at this time except what is here


SUPPORT
-------

You can find some useful information here:
http://www.zen-cart.com/modules/ipb/index.php?showtopic=31073


HISTORY
--------

You can find a list of releases (and contributors) here: history.txt


SAMPLE OUTPUT FILES
-------------------

I have dumped all of the output files from the standard zencart install into the /temp/ folder


KNOWN ISSUES
-------------
# Large numbers of Options & Attributes can cause column number to exceed display maximums in file editors (Excel etc.)
# CHMOD permissions on your temp folder of 700 may not work, depending on the config of your server. Make the permissions 777 if this is the case, and ensure that you have the index.html file in the directory if you wish to prevent browsing of your upload files.




TO DO
-----

Important:
* Meta tag support
* renovate html & finalise language support
* add help links for each error generated (include 'ep_error.php')
* Choose between create linked product on new category (default behaviour) or move to new category
* Add dates to specials summary. Put summary data (prices) in same order as in downloads file
* investigate gzip compression for uploads and downloads
* optimisation of code to reduce sql calls?


Can wait:
* Dual Price Mod support? Maybe I should just write a real-time discount by user-group mod..
* each row item explode - may improve to cater for file types other than tabs
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
