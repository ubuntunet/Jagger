<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
/**
 * ResourceRegistry3
 * 
 * @package     RR3
 * @author      Middleware Team HEAnet 
 * @copyright   Copyright (c) 2012, HEAnet Limited (http://www.heanet.ie)
 * @license     MIT http://www.opensource.org/licenses/mit-license.php
 *  
 */

/**
 * Email_sender Class
 * 
 * @package     RR3
 * @subpackage  Libraries
 * @author      Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 */
class Email_sender {

    function __construct()
    {
        $this->ci = & get_instance();
        $this->ci->load->library('doctrine');
        $this->em = $this->ci->doctrine->em;
    }

    // allow to create and use templates in following langs
    static function getLangs()
    {
        $r = array_keys(languagesCodes());
        return $r;
    }
    
    function addToMailQueue($notificationTypes = NULL, $obj = NULL, $subject, $body, $additionalReciepients, $sync = false)
    {
        $currdatetime = new \DateTime("now", new \DateTimeZone('UTC'));
        $body .= PHP_EOL . 'The message was generated by the system on ' . $currdatetime->format('Y/m/d H:i') . ' UTC' . PHP_EOL;
        $subscribers = array();
        if ($notificationTypes !== null && is_array($notificationTypes))
        {
            $notificationTypes[] = 'systemnotifications';
            $subscribers = $this->em->getRepository("models\NotificationList")->findBy(
                    array('type' => $notificationTypes, 'is_enabled' => true, 'is_approved' => true));
        }
        $alreadyMailTo = array();
        foreach ($subscribers as $s)
        {
            $type = $s->getType();
            if ($type === 'joinfedreq')
            {
                if (empty($obj))
                {
                    continue;
                }
                if (!$obj instanceOf models\Federation)
                {
                    continue;
                }
                $objId = $obj->getId();
                $fed = $s->getFederation();
                if (empty($fed))
                {
                    continue;
                }
                $fedId = $fed->getId();
                if ($fedId != $objId)
                {
                    continue;
                }
            }
            elseif ($type === 'fedmemberschanged')
            {
                if (empty($obj))
                {
                    continue;
                }
                if (is_array($obj))
                {
                    foreach ($obj as $v)
                    {
                        if ($v instanceOf models\Federation)
                        {
                            $objId = $obj->getId();
                            $fed = $s->getFederation();
                            if (empty($fed))
                            {
                                continue;
                            }
                            $fedId = $fed->getId();
                            if ($fedId != $objId)
                            {
                                continue;
                            }
                        }
                    }
                }
                elseif ($v instanceOf models\Federation)
                {
                    $objId = $obj->getId();
                    $fed = $s->getFederation();
                    if (empty($fed))
                    {
                        continue;
                    }
                    $fedId = $fed->getId();
                    if ($fedId != $objId)
                    {
                        continue;
                    }
                }
                else
                {
                    continue;
                }
            }
            elseif ($type === 'requeststoproviders')
            {
                if (!(!empty($obj) && ($obj instanceOf models\Provider)))
                {
                    continue;
                }
                $objId = $obj->getId();
                $prov = $s->getProvider();
                if (empty($prov))
                {
                    continue;
                }
                $provId = $prov->getId();
                if ($provId != $objId)
                {
                    continue;
                }
            }
            $mailto = $s->getRcpt();
            if (!in_array($mailto, $alreadyMailTo))
            {
                $m = new models\MailQueue();
                $m->setSubject($subject);
                $m->setBody($body);
                $m->setDeliveryType($s->getNotificationType());
                $m->setRcptto($mailto);
                $this->em->persist($m);
                $alreadyMailTo[] = $mailto;
            }
        }
        if (!empty($additionalReciepients) and is_array($additionalReciepients) && count($additionalReciepients) > 0)
        {
            foreach ($additionalReciepients as $v)
            {
                if (!in_array($v, $alreadyMailTo))
                {
                    $m = new models\MailQueue();
                    $m->setSubject($subject);
                    $m->setBody($body);
                    $m->setDeliveryType('mail');
                    $m->setRcptto($v);
                    $this->em->persist($m);
                    $alreadyMailTo[] = $v;
                }
            }
        }
        return true;
    }

    /**
     * $to may be single email or array of mails
     */
    function send($to, $subject, $body)
    {
        $sending_enabled = $this->ci->config->item('mail_sending_active');
        log_message('debug', 'Mail:: preparing');
        log_message('debug', 'Mail:: To: ' . serialize($to));
        log_message('debug', 'Mail:: Subject: ' . $subject);
        log_message('debug', 'Mail:: Body: ' . $body);

        if (!$sending_enabled)
        {
            log_message('debug', 'Mail:: cannot be sent because $config[mail_sending_active] is not true');
            return false;
        }
        else
        {
            log_message('debug', 'Preparing to send email');
        }
        $full_subject = $subject . " " . $this->ci->config->item('mail_subject_suffix');
        $list = array();
        if (!is_array($to))
        {
            $list[] = $to;
        }
        else
        {
            $list = $to;
        }
        $generatedAt = '';
        foreach ($list as $k)
        {
            $this->ci->email->clear();
            $this->ci->email->from($this->ci->config->item('mail_from'), '');
            $this->ci->email->to($k, '');
            $this->ci->email->subject($full_subject);
            $footer = $this->ci->config->item('mail_footer');

            $message = $body . PHP_EOL . 'Message was generated at ' . $generatedAt . PHP_EOL . $footer;
            $this->ci->email->message($message);
            if ($this->ci->email->send())
            {
                log_message('debug', 'email sent to ' . $k);
            }
            else
            {
                log_message('error', 'email couldnt be sent to ' . $k);
                log_message('error', $this->ci->email->print_debugger());
            }
        }
        return true;
    }


    static function mailTemplatesGroups()
    {
       $result = array(
        'fedregresquest'=>array('federation registration request','desclang'=>'templfedregreq','args'=>array('fedname','srcip','requsername','reqemail','token','qurl','datetimeutc')),
        'spregresquest'=>array('sp registration request','desclang'=>'templspregreq','args'=>array('token','srcip','entorgname','entityid','reqemail','requsername','reqfullname','datetimeutc','qurl')),
        'idpregresquest'=>array('idp registration request','desclang'=>'templidpregreq','args'=>array('token','srcip','entorgname','entityid','reqemail','requsername','reqfullname','datetimeutc','qurl')),
        'userregresquest'=>array('user registration request','desclang'=>'templuserregreq','args'=>array('token', 'srcip', 'reqemail', 'requsername', 'reqfullname','qurl', 'datetimeutc')),
    );
    return $result;
    }

    /**
     *  TEMPLATES
     */
    function generateLocalizedMail($group, $replacements)
    {
        $templates = $this->em->getRepository("models\MailLocalization")->findBy(array('mgroup'=>$group,'isenabled'=>TRUE));
        if(count($templates) ==0)
        {
           return null;
        }
        $tmpgroups = self::mailTemplatesGroups();
        $mygroup = $tmpgroups[''.$group.''];
        $patterns = array();
        foreach($mygroup['args'] as $a)
        {
           $patterns[''.$a.''] = '/_'.$a.'_/';
        }
        ksort($patterns);
        ksort($replacements);
        $defaultTemplate = null;
        $attachedTemplates = array();
        foreach($templates as $t)
        {
           if($t->isDefault())
           {
              $defaultTemplate = $t;
              continue;
           }  
           if($t->isAlwaysAttached())
           {
              $attachedTemplates[] = $t;
           }
        }
        if(empty($defaultTemplate))
        {
           return null;
        }
        $result = array();
        $result['subject'] = preg_replace($patterns, $replacements, $defaultTemplate->getSubject());
        $body = preg_replace($patterns, $replacements, $defaultTemplate->getBody());
        foreach($attachedTemplates as $t)
        {
           $body .= PHP_EOL.'===== '.preg_replace($patterns, $replacements, $t->getSubject()).' ===='.PHP_EOL;
           $body .= PHP_EOL.preg_replace($patterns, $replacements, $t->getBody());
        } 
        $result['body'] = $body.PHP_EOL.PHP_EOL;
        return $result;   
    }

    function providerRegRequest($type, $args, $lang = null)
    {
        $params = array(
            'requestermail' => '',
            'requestersourceip' => '',
            'serviceentityid' => '',
            'servicename' => '',
            'orgname' => '',
            'token' => '',
        );

        $merged = array_merge($params, $args);
        $isidp = false;
        $issp = false;
        if (strcasecmp($type, 'idp') == 0)
        {
            $r['subject'] = 'IDP registration request';
            $isidp = true;
        }
        elseif (strcasecmp($type, 'sp') == 0)
        {
            $r['subject'] = 'SP registration request';
            $issp = true;
        }
        else
        {
            return null;
        }

        $b = 'Dear user,' . PHP_EOL . 'You have received this mail because your email address is on the notification list' . PHP_EOL;
        if ($isidp)
        {
            $b .= $merged['requestermail'] . ' completed a new Identity Provider Registration' . PHP_EOL;
        }
        else
        {
            $b .= $merged['requestermail'] . ' completed a new Service Provider Registration' . PHP_EOL;
        }
        if (!empty($merged['requestersourceip']))
        {
            $b .= 'Request has been sent from: ' . $merged['requestersourceip'] . PHP_EOL;
        }
        if (!empty($merged['token']))
        {
            $b .= 'If you have sufficient permissions you can approve/reject it on ' . base_url() . 'reports/awaiting/detail/' . $merged['token'] . PHP_EOL;
        }
        else
        {
            $b .= 'If you have sufficient permissions you can approve/reject it on ' . base_url() . '' . PHP_EOL;
        }

        $r['body'] = $b;
        return $r;
    }

}
