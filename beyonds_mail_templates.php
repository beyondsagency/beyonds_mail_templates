<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\Module\Beyonds_mail_templates\Test as Test;

class Beyonds_Mail_Templates extends Module
{

    public function __construct()
    {
        $this->name = 'beyonds_mail_templates';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Beyonds';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->displayName = $this->l('Connexion Brevo Templates');
        $this->description = $this->l('Connexion avec l\'api Brevo pour l\'envoi de template transactionnel');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();
    }

    private function getSMTPApi()
    {
        SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', 'XXXXX');
        return new \SendinBlue\Client\Api\SMTPApi();
    }

    public function sendTemplateEmail($idLang, $template, $to, $bcc, $replyTo, $fileAttachment, $templateVars)
    {
        $sendinblueSmtpApi = $this->getSMTPApi();

        // Récupération de l'id du template Sendinblue correspondant à la langue et le nom du template PS (ex : fr_password_query)
        $templateNameSendinblue = Language::getIsoById((int)$idLang) . '_' . $template;
        $sendinblueTemplates = $sendinblueSmtpApi->getSmtpTemplates('true');

        $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail();
        if ($sendinblueTemplates->getCount() > 0) {
            foreach ($sendinblueTemplates->getTemplates() as $sendinblueTemplate) {
                if ($sendinblueTemplate['name'] == $templateNameSendinblue) {
                    $sendSmtpEmail->setTemplateId($sendinblueTemplate['id']);
                }
            }
        }

        if($sendSmtpEmail->getTemplateId()){
            $this->setTo($sendSmtpEmail, $to);
            $this->setBcc($sendSmtpEmail, $bcc);
            $this->setReplyTo($sendSmtpEmail, $replyTo);
            $this->setAttachement($sendSmtpEmail, $fileAttachment);
            $this->setParams($sendSmtpEmail, $templateVars);

            try {
                $result = $sendinblueSmtpApi->sendTransacEmail($sendSmtpEmail);

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    private function setTo(&$sendSmtpEmail, $to)
    {
        $smtpTo = [];
        if (is_array($to) && isset($to)) {
            foreach ($to as $addr) {
                if($sendSmtpEmail->getTemplateId()){
                    $sendSmtpEmailTo = new \SendinBlue\Client\Model\SendSmtpEmailTo();
                    $sendSmtpEmailTo->setEmail(self::toPunycode(trim($addr)));

                    $smtpTo[] = $sendSmtpEmailTo;
                }
            }
        } else {
            if($sendSmtpEmail->getTemplateId()){
                $sendSmtpEmailTo = new \SendinBlue\Client\Model\SendSmtpEmailTo();
                $sendSmtpEmailTo->setEmail(Mail::toPunycode($to));

                $smtpTo[] = $sendSmtpEmailTo;
            }
        }

        $sendSmtpEmail->setTo($smtpTo);
    }

    private function setBcc(&$sendSmtpEmail, $bcc)
    {
        if (isset($bcc) && is_array($bcc)) {
            $smtpBcc = [];
            foreach ($bcc as $addr) {
                if($sendSmtpEmail->getTemplateId()){
                    $sendSmtpEmailBcc = new \SendinBlue\Client\Model\SendSmtpEmailBcc();
                    $sendSmtpEmailBcc->setEmail(self::toPunycode($addr));

                    $smtpBcc[] = $sendSmtpEmailBcc;
                }
            }

            $sendSmtpEmail->setBcc($smtpBcc);
        } elseif (isset($bcc)) {
            if($sendSmtpEmail->getTemplateId()){
                $sendSmtpEmailBcc = new \SendinBlue\Client\Model\SendSmtpEmailBcc();
                $sendSmtpEmailBcc->setEmail(self::toPunycode($bcc));

                $sendSmtpEmail->setBcc([$sendSmtpEmailBcc]);
            }
        }
    }

    private function setReplyTo(&$sendSmtpEmail, $replyTo)
    {
        if (isset($replyTo) && $replyTo) {
            if($sendSmtpEmail->getTemplateId()){
                $sendSmtpEmailReplyTo = new \SendinBlue\Client\Model\SendSmtpEmailReplyTo();
                $sendSmtpEmailReplyTo->setEmail($replyTo);

                $sendSmtpEmail->setReplyTo($sendSmtpEmailReplyTo);
            }
        }
    }

    private function setAttachement(&$sendSmtpEmail, $fileAttachment){
        if ($fileAttachment && !empty($fileAttachment)) {
            $smtpAttachment = [];

            if (!is_array(current($fileAttachment))) {
                $fileAttachment = [$fileAttachment];
            }

            foreach ($fileAttachment as $attachment) {
                if (isset($attachment['content'], $attachment['name'], $attachment['mime'])) {

                    $sendSmtpEmailAttachment = new \SendinBlue\Client\Model\SendSmtpEmailAttachment();
                    $sendSmtpEmailAttachment->setName($attachment['name']);
                    $sendSmtpEmailAttachment->setContent(base64_encode($attachment['content']));

                    $smtpAttachment[] = $sendSmtpEmailAttachment;
                }
            }

            $sendSmtpEmail->setAttachment($smtpAttachment);
        }
    }

    private function setParams(&$sendSmtpEmail, $templateVars)
    {
        $vars = array();
        foreach ($templateVars as $key => $var) {
            $vars[substr($key, 1, -1)] = $var;
        }
        $sendSmtpEmail->setParams($vars);
    }

}
