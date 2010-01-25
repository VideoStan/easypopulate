<?php
/**
 * EasyPopulate Language Defintions
 *
 * @package easypopulate
 * @author langer
 * @copyright 2005-2009
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Publice License (v2 only)
 */

define('EASYPOPULATE_DISPLAY_SPLIT_LOCATION','You can also download your split files from your %s directory');

define('EASYPOPULATE_DISPLAY_FILE_NOT_EXIST','File does not exist');
define('EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED','Could not open file');
define('EASYPOPULATE_DISPLAY_FILE_SPEC','File uploaded.<br /><b>File Name: %s</b><br />Size: %s');

// product results
define('EASYPOPULATE_DISPLAY_HEADING', 'Products Summary');
define('EASYPOPULATE_DISPLAY_MODEL', 'Model');
define('EASYPOPULATE_DISPLAY_MESSAGE', 'Message');
define('EASYPOPULATE_DISPLAY_STATUS', 'Status');
define('EASYPOPULATE_DISPLAY_NAME', 'Name');
define('EASYPOPULATE_DISPLAY_PRICE', 'Price');
define('EASYPOPULATE_DISPLAY_RESULT_DELETED','DELETED');
define('EASYPOPULATE_DISPLAY_RESULT_DELETE_NOT_FOUND','NOT FOUND');
define('EASYPOPULATE_DISPLAY_RESULT_SKIPPED','SKIPPED');
define('EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NOT_FOUND', 'No category provided for this%s product');
define('EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NAME_LONG','Category name(s) too long (max. %s)');
define('EASYPOPULATE_DISPLAY_RESULT_MODEL_NAME_LONG','Model name too long');
define('EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR', 'SQL error. Check Easy Populate error log in uploads directory');
define('EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT', 'ADDED');
define('EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT_FAIL', 'ADD FAILED');
define('EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT', 'UPDATED');
define('EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL', 'UPDATE FAILED');
define('EASYPOPULATE_DISPLAY_RESULT_NO_MODEL', 'No model field in record. This line was not imported');
define('EASYPOPULATE_DISPLAY_RESULT_UPLOAD_COMPLETE', 'Upload Complete');


/**
* $messageStack defines
*/
// checks - msg stack alerts - output via $messageStack
define('EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING','<b>Easy Populate uploads folder not found!</b><br />NIX SERVERS: Your uploads folder is either missing, or you have altered the name and/or directory of your uploads folder without configuring this in Easy Populate.<br />WINDOWS SERVERS: Please request your web host to assign write permissions to the uploads folder. This is usually granted through Windows server user account IUSR_COMPUTERNAME.<br />Your configuration indicates that your uploads folder is named <b>%s</b>, and is located in <b>%s</b>, however this cannot be found.<br />Easy Populate cannot upload files until you have provided an uploads folder with read/write/execute permissions for the site owner (usually chmod 700 but you may require chmod 777)');
define('EASYPOPULATE_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS','Easy Populate successfully adjusted the permissions on your uploads folder! You can now upload files using Easy Populate...');
define('EASYPOPULATE_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS_777','Easy Populate successfully adjusted the permissions on your uploads folder, but the folder is now publically viewable. Please ensure that you added the index.html file to this directory to prevent public browsing/downloading of your Easy Populate files.');
define('EASYPOPULATE_MSGSTACK_MODELSIZE_DETECT_FAIL','Easy Populate cannot determine the maximum size permissible for the products_model field in your products table. Please ensure that the length of your model data field does not exceed the Zen Cart default value of 32 characters for any given product. Failure to heed this warning may have unintended consequences for your data.');
define('EASYPOPULATE_MSGSTACK_ERROR_SQL', 'An SQL error has occured. Please check your input data for tabs within fields and delete these. If this error continues, please forward your error log to the Easy Populate maintainer');
define('EASYPOPULATE_MSGSTACK_DROSS_DELETE_FAIL', '<b>Deleting of product data debris failed!</b> Please see the debug log in your uploads directory for further information.');
define('EASYPOPULATE_MSGSTACK_DROSS_DELETE_SUCCESS', 'Deleting of product data debris succeeded!');
define('EASYPOPULATE_MSGSTACK_DROSS_DETECTED', '<b>%s partially deleted product(s) found!</b> Delete this dross to prevent unwanted zencart behaviour by clicking <a href="%s">here.</a><br />You are seeing this because there are references in tables to a product that no longer exists, which is usually caused by an incomplete product deletion. This can cause Zen Cart to misbehave in certain circumstances.');
define('EASYPOPULATE_MSGSTACK_DATE_FORMAT_FAIL', '%s is not a valid date format. If you upload any date other than raw format (such as from Excel) you will mangle your dates. Please fix this by correcting your date format in the Easy Populate config.');

// install - msg stack alerts - output via $messageStack
define('EASYPOPULATE_MSGSTACK_INSTALL_DELETE_SUCCESS','Redundant file <b>%s</b> was deleted from <b>YOUR_ADMIN%s</b> directory.');
define('EASYPOPULATE_MSGSTACK_INSTALL_DELETE_FAIL','Easy Populate was unable to delete redundant file <b>%s</b> from <b>YOUR_ADMIN%s</b> directory. Please delete this file manually.');
define('EASYPOPULATE_MSGSTACK_INSTALL_CHMOD_FAIL','<b>Please run the Easy Populate install again after fixing your uploads directory problem.</b>');
define('EASYPOPULATE_MSGSTACK_INSTALL_CHMOD_SUCCESS','<b>Installation Successfull!</b>  A full download of your store has been done and is available in your uploads (tempEP) directory. You can use this as your 1st template for uploading/updating products.');
define('EASYPOPULATE_MSGSTACK_INSTALL_KEYS_FAIL','<b>Easy Populate Configuration Missing.</b>  Please install your configuration by clicking %shere%s');

// file handling - msg stack alerts - output via $messageStack
define('EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS', 'File <b>%s</b> successfully exported! The file is ready for download in your /%s directory.');

// html template - bottom of admin/easypopulate.php
// langer - will add after html renovation

/**
* $printsplit defines
*/
// splitting files results text - in $printsplit
define('EASYPOPULATE_FILE_SPLITS_HEADING', 'Upload split files in turn');
define('EASYPOPULATE_FILE_SPLIT_COMPLETED', 'Upload done of ');
define('EASYPOPULATE_FILE_SPLITS_DONE', 'All done!');
define('EASYPOPULATE_FILE_SPLIT_PENDING', 'Pending Upload of ');
define('EASYPOPULATE_FILE_SPLIT_ANCHOR_TEXT', 'Upload ');
// misc
define('EASYPOPULATE_FILE_SPLITS_PREFIX', 'Split-');

// Specials results
define('EASYPOPULATE_SPECIALS_HEADING', 'Specials Summary');
define('EASYPOPULATE_SPECIALS_PRICE', 'Specials Price');
define('EASYPOPULATE_SPECIALS_PRICE_FAIL', 'Specials price higher than normal price');
define('EASYPOPULATE_SPECIALS_DELETE_FAIL', 'Can\t delete special');

// error log defines - for ep_debug_log.txt
//define('EASYPOPULATE_ERRORLOG_SQL_ERROR', 'MySQL error %s: %s\nWhen executing:\n%sn');
?>
