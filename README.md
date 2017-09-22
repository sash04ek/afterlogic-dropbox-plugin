afterlogic-dropbox-plugin
=========================
Plugin for AfterLogic WebMail Pro

This plugin extends Files Storage functionality of WebMail Pro by adding interface to user's Dropbox account. With this plugin enabled, and once user has Google account access configured, the user will get an additional "Google Drive" tab under Files screen, with the same set of features as the one available for existing Personal files storage.

To allow for using this feature, administrator of WebMail Pro installation needs to configure Dropbox integration first:

http://www.afterlogic.com/wiki/Configuring_integration_with_social_networks_(WebMail)#Dropbox

The plugin itself is enabled in a standard way for WebMail Pro described at:

http://www.afterlogic.com/wiki/Installing_a_plugin_(WebMail)

The following item needs to be added in array defined in data/settings/config.php file:

'plugins.afterlogic-dropbox-plugin' => true,

The Dropbox PHP SDK can be installed through Composer.

$ php composer require kunalvarma05/dropbox-php-sdk