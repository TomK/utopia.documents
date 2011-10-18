<?php

define('DOC_TYPE_PRINT',flag_gen('DOC_TYPE'));
define('DOC_TYPE_EMAIL',flag_gen('DOC_TYPE'));

class tabledef_Documents extends uTableDef {
	public $tablename = 'documents';

	public function SetupFields() {
		// add all the fields in here
		// AddField($name, $type, $length, $collation='', $attributes='', $null='not null', $default='', $extra='', $comments='')
		// SetPrimaryKey($name);

		$this->AddField('identifier',ftVARCHAR,60);
		$this->AddField('type',ftNUMBER);
		$this->AddField('subject',ftVARCHAR,100);
		$this->AddField('body',ftLONGTEXT);

		$this->SetPrimaryKey('identifier');
	}
}
class tabledef_DocumentAttachments extends uTableDef {
	public function SetupFields() {
		$this->AddField('attachment_id',ftNUMBER);
		$this->AddField('doc_id',ftVARCHAR,60);
		$this->AddField('attachment',ftFILE);

		$this->SetPrimaryKey('attachment_id');
	}
}

class uDocumentDetails extends uSingleDataModule implements iAdminModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Edit Document'; } //$row = $this->GetRecord($this->GetDataset(),0); return $row['name']; }
	public function GetOptions() { return ALLOW_FILTER | ALLOW_ADD | ALLOW_EDIT; }
	public function GetTabledef() { return 'tabledef_Documents'; }

	public function SetupParents() {
		$this->AddParent('uDocumentList','ident','*');
	}

	public function SetupFields() {
		$this->CreateTable('docs');

		$this->AddField('ident','identifier','docs','Ident',itTEXT);
		$this->AddField('type','type','docs','Type',itCOMBO,array('Letter'=>DOC_TYPE_PRINT,'Email'=>DOC_TYPE_EMAIL));
		$this->AddField('subject','subject','docs','Subject',itTEXT);
		$this->AddField('body','body','docs','Body',itHTML);
		$this->FieldStyles_Set('body',array('width'=>'100%','height'=>'20em'));
	}

	public function RunModule() {
		$this->ShowData();

//		uDocuments::toEmail('test',array('email'=>'oridan82@gmail.com'),'email');
	}
}
class uDocumentList extends uListDataModule implements iAdminModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Emails & Documents'; } //$row = $this->GetRecord($this->GetDataset(),0); return $row['name']; }
	public function GetOptions() { return ALLOW_FILTER | ALLOW_DELETE; }
	public function GetTabledef() { return 'tabledef_Documents'; }

	public function SetupParents() {
		$this->AddParent('internalmodule_Admin');
	}

	public function SetupFields() {
		$this->CreateTable('docs');

		$this->AddField('ident','identifier','docs','Ident');
		$this->AddField('subject','subject','docs','Subject');
	}

	public function RunModule() {
		$this->ShowData();
	}
}
class uDocumentAttachmentList extends uListDataModule implements iAdminModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Attachments'; }
	public function GetOptions() { return ALLOW_ADD | ALLOW_EDIT | ALLOW_DELETE; }
	public function GetTabledef() { return 'tabledef_DocumentAttachments'; }

	public function SetupParents() {
		$this->AddParent('uDocumentDetails',array('ident'=>'doc_id'));
		$this->AddParentCallback('uDocumentDetails',array($this,'ParentLoad'),1);
	}

	public function ParentLoad($p) {
		$this->ShowData();
	}
	public function SetupFields() {
		$this->CreateTable('docs');

		$this->AddField('doc_id','doc_id','docs','docid');
		$this->AddField('attachment','attachment','docs','Attachment',itFILE);
	}

	public function RunModule() {
		$this->ShowData();
	}
}
class uDocuments extends uDataModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return ''; } //$row = $this->GetRecord($this->GetDataset(),0); return $row['name']; }
	public function GetOptions() { return ALWAYS_ACTIVE | ALLOW_FILTER | PERSISTENT_PARENT; }
	public function GetTabledef() { return 'tabledef_Documents'; }

	public function SetupParents() {
		//$this->AddParent('*');
		$this->RegisterAjax('getDoc',array($this,'getDoc'));
		modOpts::AddOption('smtp','host','SMTP Host');
		modOpts::AddOption('smtp','port','SMTP Port',26);
		modOpts::AddOption('smtp','user','SMTP Username','yourname@yourdomain');
		modOpts::AddOption('smtp','pass','SMTP Password','',itPLAINPASSWORD);
		modOpts::AddOption('mailer','name','Mailer Default Name',utopia::GetDomainName().' Mailer');
		modOpts::AddOption('mailer','email','Mailer Default Email','mailer@'.utopia::GetDomainName());
	}

	public function SetupFields() {
		$this->CreateTable('docs');

		$this->AddField('ident','identifier','docs','Ident');
		$this->AddField('subject','subject','docs','Subject');
		$this->AddField('type','type','docs','Type');
		$this->AddField('body','body','docs','Body');
	}

/*	public function ParentLoad($parent) {
		if (!is_subclass_of($parent,'uListDataModule')) return;
		$obj = utopia::GetInstance($parent);
		$url = self::BuildURL($obj->GetURL());
		utopia::LinkList_Add('list_functions:'.$parent,'Documents',$url,10,NULL,array('target'=>'_blank','class'=>'fdb-btn bluebg'));
	}*/

	public function RunModule() { }

	public function ShowData() { }

	public static function BuildURL($url,$docIdent=NULL,$docType=NULL) {
		return BuildQueryString($url,array('__ajax'=>'getDoc','docIdent'=>$docIdent));
	}

	public function getDoc() {
		if (!array_key_exists('docIdent',$_GET)) {
			utopia::UseTemplate();
			$docDataset = $this->GetDataset(); $i=0;
			echo "<p>Select which document to work with.</p>";
			echo '<form method="get" action="">';
			$pairs = GetQSPairs($_SERVER['REQUEST_URI']);
			foreach ($pairs as $key=>$val)
			echo '<input type="hidden" name="'.$key.'" value="'.$val.'">'."\n";
			echo '<select name="docIdent">';
			while (($row = $this->GetRecord($docDataset,$i))) {
				echo '<option value="'.$row['ident'].'">'.$row['subject'].'</option>';
				$i++;
			}
			echo '</select> <input type="submit"></form>';
			return;
		}

		$doc = $this->LookupRecord(array('ident' => $_GET['docIdent']));
		// got doc, what type?
/*		if (!array_key_exists('docType',$_GET)) {
			utopia::UseTemplate();
			echo 'We are working with <b>&quot;'.$doc['subject'].'&quot;</b>';
			echo "<p>What would you like to do with the document?</p>";
			echo '<form method="get" action="">';
			$pairs = GetQSPairs($_SERVER['REQUEST_URI']);
			foreach ($pairs as $key=>$val)
			echo '<input type="hidden" name="'.$key.'" value="'.$val.'">'."\n";
			echo '<select name="docType">';
			echo '<option value="p">Print</option>';
			echo '<option value="e">Email</option>';
			echo '</select> <input type="submit"></form>';
			return;
		}*/

		$obj = utopia::GetInstance(utopia::GetCurrentModule());
		$dataset = $obj ->GetDataset();
		$data = GetRows($dataset);
		switch (intval($doc['type'])) {
			case DOC_TYPE_PRINT:
				return self::toPrint($_GET['docIdent'],$data);
			case DOC_TYPE_EMAIL:
				utopia::UseTemplate();
				$fields = $obj->fields;
				if (!array_key_exists('docEmailField',$_GET) || !array_key_exists('docFromName',$_GET) || !array_key_exists('docFromEmail',$_GET)) { // email field and from
					echo 'We are sending an email with <b>&quot;'.$doc['subject'].'&quot;</b>';
					echo "<p>We need some more information.</p>";
					echo '<form method="get" action="">';
//					$pairs = GetQSPairs($_SERVER['REQUEST_URI']);
					foreach ($_GET as $key=>$val)
					echo '<input type="hidden" name="'.$key.'" value="'.$val.'">'."\n";
					echo '<select name="docEmailField">';
					foreach ($fields as $fieldName => $fieldInfo) {
						if (empty($fieldInfo['visiblename']))
							echo '<option value="e">Email</option>';
					}
					echo '</select> <input type="submit"></form>';
					return;
				}
				return self::toEmail($_GET['docIdent'],$data,$_GET['docEmailField'],$_GET['docFromName'],$_GET['docFromEmail']);
			default: echo 'Unknown type'; return;
		}
	}

	public static function GetDocument($ident) {
		$obj = utopia::GetInstance(__CLASS__);
		$row = $obj->LookupRecord(array('ident'=>$ident));
		// if no doc, create it and alert admin
		if (!$row) {
			$pk = null;
			$obj->UpdateField('ident',$ident,$pk);
			DebugMail('Document Not Found','No document found called '.$ident.'.  This has been created automatically.');
			$row = $obj->LookupRecord($pk);
		}

		return $row;
	}

	public static function toEmail($ident,$data,$emailField,$fromName=null,$fromEmail=null) {
		$row = self::GetDocument($ident);

		if (!array_key_exists(0,$data)) $data = array($data);

		$mail = new PHPMailer(true); // throw exceptions
		$fromName = $fromName ? $fromName : modOpts::GetOption('mailer','name');
		$fromEmail = $fromEmail ? $fromEmail : modOpts::GetOption('mailer','email');
		$mail->SetFrom($fromEmail,$fromName);

		if (modOpts::GetOption('smtp','host')) {
			$mail->IsSMTP(); // telling the class to use SMTP
			$mail->SMTPAuth   = true;	// enable SMTP authentication
			$mail->Host       = modOpts::GetOption('smtp','host');	// sets the SMTP server
			$mail->Port       = modOpts::GetOption('smtp','port');	// set the SMTP port for the GMAIL server
			$mail->Username   = modOpts::GetOption('smtp','user');	// SMTP account username
			$mail->Password   = modOpts::GetOption('smtp','pass');	// SMTP account password
		}
		$mail->IsHTML(true);
		$mail->AddCustomHeader("X-MSMail-Priority: Medium\r\n");
		$mail->AddCustomHeader("Importance: Medium\r\n");

		$obj = utopia::GetInstance('uDocumentAttachmentList');
		$attachments = $obj->GetRows(array('doc_id'=>$ident));
		foreach ($attachments as $attachment) {
			$mail->AddStringAttachment($attachment['attachment'],$attachment['attachment_filename'],'base64',$attachment['attachment_filetype']);
		}

		try {
			foreach ($data as $item) {
				$mail->ClearAllRecipients();
				$mail->AddAddress($item[$emailField]);
				$mail->Subject = self::ReplaceData($item,$row['subject']);
				$mail->MsgHTML(self::ReplaceData($item,$row['body']));
				$mail->Send();
			}
		} catch (phpmailerException $e) {
			 DebugMail('Email Error',$e->errorMessage());
		} catch (Exception $e) {
			 DebugMail('Email Error',$e->getMessage());
		}
	}

	public static function toPrint($ident,$data) {
		$row = self::GetDocument($ident);
		if (!$row) return;

		$subject = $row['subject'];
		if (!array_key_exists(0,$data)) $subject = self::ReplaceData($data,$subject);
		utopia::SetVar('title',$subject);

		utopia::CancelTemplate();

		$dompdf = new DOMPDF();
		$dompdf->load_html($row['body']);
		$dompdf->render();
		//$dompdf->output();
		$dompdf->stream($subject.".pdf");

		return;		// current only single documents are supported - no attachments

		if (array_key_exists(0,$data)) { // multipage
			$fullOut = '';
			foreach ($data as $item) {
				$fullOut .= '<div style="position:relative">'.self::ReplaceData($item,$row['body']).'</div><div style="page-break-after: always;"></div>';
				//self::ReplaceData($item,$row['body'])
			}

			echo $fullOut;
			return;
		}

		echo self::ReplaceData($data,$row['body']);
	}

	public static function ReplaceData($pairs,$text,$encode=false) {
		foreach ($pairs as $field=>$value) {
			if ($encode) $value = htmlspecialchars($value);
			$text = str_replace("[[$field]]",str_replace("\n",'<w:br/>',$value),$text);
		}
		return $text;
	}
}

?>
