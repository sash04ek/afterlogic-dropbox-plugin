<?php

/*
 * Copyright (C) 2002-2013 AfterLogic Corp. (www.afterlogic.com)
 * Distributed under the terms of the license described in LICENSE
 *
 */

class_exists('CApi') or die();

include_once 'libs/Dropbox/autoload.php';

class CFilestorageDropboxPlugin extends AApiPlugin
{
	const StorageType = 4;
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
			$this->oSocial = $oApiSocial->GetSocial($oAccount->IdAccount, \ESocialType::Dropbox);
		}
		return $this->oSocial;
	}
	
	protected function GetClient($oAccount, $iType)
	{
		$mResult = false;
		if ($iType === self::StorageType)
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
			if ($oSocial && $oTenant && $oTenant->SocialDropboxAllow)
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
				'Type' => self::StorageType,
				'DisplayName' => self::DisplayName
			);
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function FileExists($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	protected function PopulateFileInfo($oAccount, $iType, $oClient, $aData)
	{
		$bResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->_dirname($aData['path']), '/');
			
			$oSocial = $this->GetSocial($oAccount);
			$bResult /*@var $bResult \CFileStorageItem */ = new  \CFileStorageItem();
			$bResult->IsExternal = true;
			$bResult->Type = $iType;
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
				'Type' => $iType,
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
	public function GetFileInfo($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
		if ($oClient)
		{
			$bBreak = true;
			$aData = $oClient->getMetadata('/'.ltrim($sPath, '/').'/'.$sName);
			$bResult = $this->PopulateFileInfo($oAccount, $iType, $oClient, $aData);
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFile($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	public function GetFiles($oAccount, $iType, $sPath, $sPattern, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
				$oItem /*@var $oItem \CFileStorageItem */ = $this->PopulateFileInfo($oAccount, $iType, $oClient, $aChild);
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
	public function CreateFolder($oAccount, $iType, $sPath, $sFolderName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	public function CreateFile($oAccount, $iType, $sPath, $sFileName, $mData, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	
	public function CreatePublicLink($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
		if ($oClient)
		{
			$bBreak = true;
			$bResult = $oClient->createShareableLink('/'.ltrim($sPath, '/').'/'.$sName);
		}
	}	

	public function DeletePublicLink($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	public function Delete($oAccount, $iType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	public function Rename($oAccount, $iType, $sPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iType);
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
	public function Move($oAccount, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iFromType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($iToType === $iFromType)
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
	public function Copy($oAccount, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $iFromType);
		if ($oClient)
		{
			$bResult = false;
			$bBreak = true;
			
			if ($iToType === $iFromType)
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