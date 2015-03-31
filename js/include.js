(function () {

	AfterLogicApi.addPluginHook('view-model-defined', function (sViewModelName, oViewModel) {

		if (oViewModel && ('CServicesSettingsViewModel' === sViewModelName))
		{
			var 
				sSocialDropboxScopes = AfterLogicApi.getAppDataItem('SocialDropboxScopes'),
				bFilestorageScope = (sSocialDropboxScopes ? (sSocialDropboxScopes.indexOf("filestorage") > -1) : false)
			;
			oViewModel.allowDropbox = AfterLogicApi.getAppDataItem('SocialDropbox') && bFilestorageScope;
			oViewModel.dropboxConnected = ko.observable(false);

			oViewModel.onDropboxSignInClick = function ()
			{
				if (!oViewModel.dropboxConnected())
				{
					oViewModel.onSocialSignInClick('dropbox');
				}
				else
				{
					oViewModel.onSocialSignOutClick('dropbox');
				}
			};
		}
	});
	
}());