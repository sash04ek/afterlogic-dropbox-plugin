<?php

class_exists('CApi') or die();

class CFilestorageDropboxPlugin extends AApiPlugin
{
	const StorageType = 4;
	const StorageTypeStr = 'dropbox';
	const DisplayName = 'Dropbox';
	
	/* @var $oSocial \CSocial */
	public $oSocial = null;
	
	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	public function __construct(CApiPluginManager $oPluginManager)
	{
		parent::__construct('1.0', $oPluginManager);
	}
 
	public function Init()
	{
		parent::Init();
		
		$this->AddCssFile('css/style.css');
		
		$this->AddHook('filestorage.get-external-storages', 'GetExternalStorage');
		$this->AddHook('filestorage.file-exists', 'FileExists');
		$this->AddHook('filestorage.get-file-info', 'GetFileInfo');
		$this->AddHook('filestorage.get-file', 'GetFile');
		$this->AddHook('filestorage.get-files', 'GetFiles');
		$this->AddHook('filestorage.create-folder', 'CreateFolder');
		$this->AddHook('filestorage.create-file', 'CreateFile');
		$this->AddHook('filestorage.create-public-link', 'CreatePublicLink');
		$this->AddHook('filestorage.delete-public-link', 'DeletePublicLink');
		$this->AddHook('filestorage.delete', 'Delete');
		$this->AddHook('filestorage.rename', 'Rename');
		$this->AddHook('filestorage.move', 'Move');
		$this->AddHook('filestorage.copy', 'Copy');
	}
	
	protected function GetSocial($oAccount)
	{
		if (!isset($this->oSocial))
		{
			/* @var $oApiSocial \CApiSocialManager */
			$oApiSocial = \CApi::Manager('social');
			$mResult = $oApiSocial->GetSocial($oAccount->IdAccount, self::StorageTypeStr);
			if ($mResult !== null && $mResult->IssetScope('filestorage')&& !$mResult->Disabled)
			{
				$this->oSocial = $mResult;
			}
		}
		return $this->oSocial;
	}
	
	protected function GetClient($oAccount, $sType)
	{
		$mResult = false;
		if ($sType === self::StorageTypeStr)
		{
			/* @var $oTenant \CTenant */
			$oTenant = null;
			$oApiTenants = \CApi::Manager('tenants');
			if ($oAccount && $oApiTenants)
			{
				$oTenant = (0 < $oAccount->IdTenant) ? $oApiTenants->GetTenantById($oAccount->IdTenant) :
					$oApiTenants->GetDefaultGlobalTenant();
			}
			
			/* @var $oSocial \CSocial */
			$oSocial = $this->GetSocial($oAccount);
			
			$oTenantSocial = null;
			if ($oTenant)
			{
				/* @var $oTenantSocial \CSocial */
				$oTenantSocial = $oTenant->GetSocialByName('dropbox');
			}
			if ($oSocial && $oTenantSocial && $oTenantSocial->SocialAllow && $oTenantSocial->IssetScope('filestorage'))
			{
				
				$oDropboxApp = new \Kunnu\Dropbox\DropboxApp(
					$oTenantSocial->SocialId,
					$oTenantSocial->SocialSecret,
					$oSocial->AccessToken
				);
				$mResult = new \Kunnu\Dropbox\Dropbox($oDropboxApp);
			}
		}
		
		return $mResult;
	}	

	public function GetExternalStorage($oAccount, &$aResult)
	{
		if ($this->GetSocial($oAccount))
		{
			$aResult[] = array(
				'Type' => self::StorageTypeStr,
				'DisplayName' => self::DisplayName
			);
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function FileExists($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($oClient->getMetadata($sPath.'/'.$sName))
			{
				$bResult = true;
			}
		}
	}	

	protected function _dirname($sPath)
	{
		$sPath = dirname($sPath);
		return str_replace(DIRECTORY_SEPARATOR, '/', $sPath); 
	}
	
	protected function _basename($sPath)
	{
		$aPath = explode('/', $sPath);
		return end($aPath); 
	}
	
	protected function hasThumb($sName)
	{
		return in_array(
			pathinfo($sName, PATHINFO_EXTENSION), 
			['jpg', 'jpeg', 'png', 'tiff', 'tif', 'gif', 'bmp']
		);
	}	

	/**
	 * @param array $aData
	 */
	protected function PopulateFileInfo($oAccount, $sType, $oClient, $aData)
	{
		$mResult = false;
		if ($aData)
		{
			$sPath = ltrim($this->_dirname($aData->getPathDisplay()), '/');
			
			$oSocial = $this->GetSocial($oAccount);

			$mResult /*@var $mResult \Aurora\Modules\Files\Classes\FileItem */ = new \CFileStorageItem();
			$mResult->IsExternal = true;
			$mResult->TypeStr = self::StorageTypeStr;
			$mResult->IsFolder = ($aData instanceof \Kunnu\Dropbox\Models\FolderMetadata);
			$mResult->Id = $aData->getName();
			$mResult->Name = $mResult->Id;
			$mResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$mResult->Size = !$mResult->IsFolder ? $aData->getSize() : 0;
//			$bResult->Owner = $oSocial->Name;
			if (!$mResult->IsFolder)
			{
				$mResult->LastModified =  date("U",strtotime($aData->getServerModified()));
			}
//			$mResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$mResult->FullPath = $mResult->Name !== '' ? $mResult->Path . '/' . $mResult->Name : $mResult->Path ;
			$mResult->ContentType = api_Utils::MimeContentType($mResult->Name);
			
			$mResult->Thumb = $this->hasThumb($mResult->Name);			
			
			$mResult->Hash = \CApi::EncodeKeyValues(array(
				'Type' => $sType,
				'Path' => $mResult->Path,
				'Name' => $mResult->Name,
				'Size' => $mResult->Size
			));
/*				
			if (!$oItem->IsFolder && $aChild['thumb_exists'])
			{
				$oItem->Thumb = true;
				$aThumb = $oClient->getThumbnail($aChild['path'], "png", "m");
				if ($aThumb && isset($aThumb[1]))
				{
					$oItem->ThumbnailLink = "data:image/png;base64," . base64_encode($aThumb[1]);
				}
			}
*/
			
		}
		return $mResult;
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFileInfo($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			$aData = $oClient->getMetadata($sPath.'/'.$sName);
			$bResult = $this->PopulateFileInfo($oAccount, $sType, $oClient, $aData);
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFile($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			
			$mDownloadResult = $oClient->download($sPath.'/'.$sName);
			if ($mDownloadResult)
			{
				$bResult = \fopen('php://memory','r+');
				\fwrite($bResult, $mDownloadResult->getContents());
				\rewind($bResult);
			}
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFiles($oAccount, $sType, $sPath, $sPattern, &$mResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$mResult = array();
			$bBreak = true;
			$aItems = array();
			if (empty($sPattern))
			{
				$oListFolderContents = $oClient->listFolder($sPath);
				$oItems = $oListFolderContents->getItems();
				$aItems = $oItems->all();
			}
			else
			{
				$oListFolderContents = $oClient->search($sPath, $sPattern);
				$oItems = $oListFolderContents->getItems();
				$aItems = $oItems->all();
			}

			foreach($aItems as $oChild) 
			{
				if ($oChild instanceof \Kunnu\Dropbox\Models\SearchResult)
				{
					$oChild = $oChild->getMetadata();
				}
				$oItem /*@var $oItem \Aurora\Modules\Files\Classes\FileItem */ = $this->PopulateFileInfo($oAccount, $sType, $oClient, $oChild);
				if ($oItem)
				{
					$mResult[]  = $oItem;
				}
			}				
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function CreateFolder($oAccount, $sType, $sPath, $sFolderName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;

				if ($oClient->createFolder($sPath.'/'.$sFolderName) !== null)
				{
					$bResult = true;
				}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function CreateFile($oAccount, $sType, $sPath, $sFileName, $mData, &$mResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$mResult = false;
			$bBreak = true;

			$Path = $sPath.'/'.$sFileName;
			$rData = $mData;
			if (!is_resource($mData))
			{
				$rData = fopen('php://memory','r+');
				fwrite($rData, $mData);
				rewind($rData);					
			}
			$oDropboxFile = \Kunnu\Dropbox\DropboxFile::createByStream($sFileName, $rData);
			if ($oClient->upload($oDropboxFile,	$Path))
			{
				$mResult = true;
			}
		}
	}	
	
	public function CreatePublicLink($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			$bResult = $oClient->createShareableLink('/'.ltrim($sPath, '/').'/'.$sName);
		}
	}	

	public function DeletePublicLink($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			$sFile = '/'.ltrim($sPath, '/').'/'.$sName;
			$sFileTmp = $sFile . '-$tmp';
			try
			{
				$oClient->copy($sFile, $sFileTmp);
				$oClient->delete($sFile);
				$oClient->move($sFileTmp, $sFile);
				$bResult = true;
			} 
			catch (Exception $ex) 
			{
				$bResult = false;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Delete($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			
			$oClient->delete($sPath.'/'.$sName);
			$bResult = true;
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Rename($oAccount, $sType, $sPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($oClient->move($sPath.'/'.$sName, $sPath.'/'.$sNewName))
			{
				$bResult = true;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Move($oAccount, $sFromType, $sToType, $sFromPath, $sToPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sFromType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($sToType === $sFromType)
			{
				if ($oClient->move($sFromPath.'/'.$sName, $sToPath.'/'.$sNewName))
				{
					$bResult = true;
				}
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Copy($oAccount, $sFromType, $sToType, $sFromPath, $sToPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sFromType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($sToType === $sFromType)
			{
				if ($oClient->copy($sFromPath.'/'.$sName, $sToPath.'/'.$sNewName))
				{
					$bResult = true;
				}
			}
		}
	}	
}

return new CFilestorageDropboxPlugin($this);