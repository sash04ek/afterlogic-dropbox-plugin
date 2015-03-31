<?php

/* -AFTERLOGIC LICENSE HEADER- */

class_exists('CApi') or die();

include_once 'libs/Dropbox/autoload.php';

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
		
		$this->AddJsFile('js/include.js');

		$this->AddCssFile('css/style.css');
		
		$this->IncludeTemplate('Settings_ServicesSettingsViewModel', 'ServicesSettings-Before-Buttons', 'templates/index.html');
		
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
			if ($mResult !== null && $mResult->IssetScope('filestorage'))
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
				$mResult = new \Dropbox\Client($oSocial->AccessToken, "Aurora App");
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
			
			if ($oClient->getMetadata('/'.ltrim($sPath, '/').'/'.$sName))
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

	/**
	 * @param array $aData
	 */
	protected function PopulateFileInfo($oAccount, $sType, $oClient, $aData)
	{
		$bResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->_dirname($aData['path']), '/');
			
			$oSocial = $this->GetSocial($oAccount);
			$bResult /*@var $bResult \CFileStorageItem */ = new  \CFileStorageItem();
			$bResult->IsExternal = true;
			$bResult->TypeStr = $sType;
			$bResult->IsFolder = $aData['is_dir'];
			$bResult->Id = $this->_basename($aData['path']);
			$bResult->Name = $bResult->Id;
			$bResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$bResult->Size = $aData['bytes'];
			$bResult->Owner = $oSocial->Name;
			$bResult->LastModified = date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$bResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$bResult->FullPath = $bResult->Name !== '' ? $bResult->Path . '/' . $bResult->Name : $bResult->Path ;
			
			$bResult->Hash = \CApi::EncodeKeyValues(array(
				'Type' => $sType,
				'Path' => $bResult->Path,
				'Name' => $bResult->Name,
				'Size' => $bResult->Size
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
		return $bResult;
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
			$aData = $oClient->getMetadata('/'.ltrim($sPath, '/').'/'.$sName);
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
			
			$bResult = fopen('php://memory','wb+');
			$oClient->getFile('/'.ltrim($sPath, '/').'/'.$sName, $bResult);
			rewind($bResult);
			
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFiles($oAccount, $sType, $sPath, $sPattern, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bResult = array();
			$bBreak = true;
			
			$aItems = array();
			$sPath = '/'.ltrim($sPath, '/');
			if (empty($sPattern))
			{
				$aItem = $oClient->getMetadataWithChildren($sPath);
				$aItems = $aItem['contents'];
			}
			else
			{
				$aItems = $oClient->searchFileNames($sPath, $sPattern);
			}
			
			foreach($aItems as $aChild) 
			{
				$oItem /*@var $oItem \CFileStorageItem */ = $this->PopulateFileInfo($oAccount, $sType, $oClient, $aChild);
				if ($oItem)
				{
					$bResult[] = $oItem;
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

			if ($oClient->createFolder('/'.ltrim($sPath, '/').'/'.$sFolderName) !== null)
			{
				$bResult = true;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function CreateFile($oAccount, $sType, $sPath, $sFileName, $mData, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;

			$sPath = '/'.ltrim($sPath, '/').'/'.$sFileName;
			if (is_resource($mData))
			{
				if ($oClient->uploadFile($sPath, \Dropbox\WriteMode::add(), $mData))
				{
					$bResult = true;
				}
			}
			else
			{
				if ($oClient->uploadFileFromString($sPath, \Dropbox\WriteMode::add(), $mData))
				{
					$bResult = true;
				}
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
			$bResult = false;
			$bBreak = true;
			
			if ($oClient->delete('/'.ltrim($sPath, '/').'/'.$sName))
			{
				$bResult = true;
			}
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
			
			$sPath = ltrim($sPath, '/');
			if ($oClient->move('/'.$sPath.'/'.$sName, '/'.$sPath.'/'.$sNewName))
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
				if ($oClient->move('/'.ltrim($sFromPath, '/').'/'.$sName, '/'.ltrim($sToPath, '/').'/'.$sNewName))
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
				if ($oClient->copy('/'.ltrim($sFromPath, '/').'/'.$sName, '/'.ltrim($sToPath, '/').'/'.$sNewName))
				{
					$bResult = true;
				}
			}
		}
	}	
}

return new CFilestorageDropboxPlugin($this);