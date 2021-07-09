<?php declare(strict_types=1);

namespace ProcessWire;

/**
 * A WireMail module for ProcessWire CMF/CMS
 * =========================================
 *
 * Example Usage
 * -------------
 *
 * $email = new WireMailPostmark();
 * $email->to = 'recipient@somedomain.com';
 * $email->subject = 'Test #1';
 * $email->body = 'An example email';
 * $email->send();
 *
 *
 *
 * This file is released under the following license:
 *
 * The MIT License (MIT)
 * =====================
 *
 * Copyright © 2021-present Netcarver & Nifty Solutions
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


class WireMailPostmark extends WireMail implements Module, ConfigurableModule
{
    const MYCLASS = 'WireMailPostmark';
    const MIN_PHP = '7.2.0';



    static $post_client   = null;
    static $get_client    = null;
    static $status_client = null;
    static $est_date      = null;



    public static function getModuleInfo() {
        return [
            'name'        => self::MYCLASS,
            'title'       => 'WireMail for Postmark',
            'author'      => 'Netcarver & Pete of Nifty Solutions',
            'summary'     => 'Allows Processwire to send transactional email via Postmark',
            'href'        => 'https://postmarkapp.com',
            'version'     => '0.4.0',
            'autoload'    => true,
            'singular'    => false,
            'permanent'   => false,
            'requires'    => [
                'ProcessWire>3.0.33',
                'PHP>='.self::MIN_PHP,
            ],
        ];
    }



    public function __construct() {
        parent::__construct();
        $defaults = self::getDefaultConfig();
		$config   = wire('modules')->getModuleConfigData($this);
        $merged   = array_merge($defaults, $config);
		foreach ($merged as $key => $value){
			/* $this->set($k, $v); */
			$this->$key = $value;
		}

        $blank = self::blankEmail();
        $mail  = $this->mail;
        $merged = array_merge($blank, $mail);
        $this->mail = $merged;
    }



    public function ___install() {
        if (version_compare(phpversion(), self::MIN_PHP, '<')) {
            throw new WireException(self::MYCLASS . " requires PHP >= " . self::MIN_PHP . " to work.");
        }
    }



    /**
     * Quick link to the edit page for the module.
     */
    public function getModuleConfigUrl(): string {
        return $this->config->urls->admin . "module/edit?name=" . self::MYCLASS;
    }



    public static function getDefaultConfig() {
        return [
            'server_token'     => '',
            'sender_signature' => '',
            'track_flags'      => [],
        ];
    }



    public function init() {
    }



    protected static function blankEmail(): array {
        $blank = [
            'to'          => [],
            'toName'      => [],
            'from'        => '',
            'fromName'    => '',
            'replyTo'     => '',
            'replyToName' => '',
            'subject'     => '',
            'body'        => '',
            'bodyHTML'    => '',
            'ccs'         => [],
            'bccs'        => [],
            'tag'         => '',
            'meta'        => [],
            'attachments' => [],
            'stream'      => 'outbound',
        ];
        return $blank;
    }



    public function tag($value) {
        $this->mail['tag'] = $this->sanitizer->name($value);
        return $this;
    }



    public function meta(array $value = []) {
        $this->mail['meta'] = $value;
        return $this;
    }



    public function stream(string $value) {
        if ('inbound' === strtolower($value)) throw new \Exception('Email send stream cannot be set to "inbound"');
        $this->mail['stream'] = $value;
        return $this;
    }



    protected static function initClients($token) {
        if (null === self::$post_client) {
            $post_client = new WireHttp();
            if (!$post_client) throw new \Exception("Could not create a postmark post client.");
            $post_client->setHeader('X-Postmark-Server-Token', $token);
            $post_client->setHeader('X-Mailer', 'ProcessWire/WireMailPostmark');
            $post_client->setHeader('Content-Type', 'application/json');
            $post_client->setHeader('Accept', 'application/json');
            self::$post_client = $post_client;
        }

        if (null === self::$get_client) {
            $get_client = new WireHttp();
            if (!$get_client) throw new \Exception("Could not create a postmark get client.");
            $get_client->setHeader('X-Postmark-Server-Token', $token);
            $get_client->setHeader('Accept', 'application/json');
            self::$get_client = $get_client;
        }

    }



    public function getPostmarkServiceStatus() {
        if (null === self::$status_client) {
            $status_client = new WireHttp();
            if (!$status_client) throw new \Exception("Could not create a postmark service get client.");
            $status_client->setHeader('Accept', 'application/json');
            self::$status_client = $status_client;
        }

        return json_decode(self::$status_client->get('https://status.postmarkapp.com/api/1.0/status'));
    }



    /**
     * Retrieve information about either the current email or the email with the given ID.
     *
     * Allows for checking for delivery via the actions member of the result object.
     */
    protected function getEmailInfo($id = null) {
        $token = $this->server_token;
        self::initClients($token);

        if (empty($id)) {
            $id = $this->email_id;
        }
        if (!is_string($id) || empty($id)) {
            throw new \Exception("No email ID provided for status fetch.");
        }

        $result = json_decode(
            self::$get_client->get("https://api.postmarkapp.com/messages/outbound/$id/details")
        );

        return $result;
    }



    protected function getServerInfo() {
        $token = $this->server_token;
        self::initClients($token);

        $result = json_decode(
            self::$get_client->get("https://api.postmarkapp.com/server")
        );

        return $result;
    }



    public function getServerSendStats(string $tag = '', $from_ts = 0, $to_ts = 0) {
        $token = $this->server_token;
        self::initClients($token);

        $url = "https://api.postmarkapp.com/stats/outbound/";
        $qs = [];
        if (!empty($tag)) {
            $tag = urlencode($tag);
            $qs[] = "tag=$tag";
        }

        if ($from_ts || $to_ts) {
            if (null === $this->est_date) {
                $this->est_date = new \DateTime("now", new \DateTimeZone('EST'));
            }
            if ($from_ts) {
                $qs[] = 'fromdate=' . $this->est_date->setTimestamp($from_ts)->format('Y-m-d');
            }
            if ($to_ts) {
                $qs[] = 'todate=' . $this->est_date->setTimestamp($to_ts)->format('Y-m-d');
            }
        }

        $qs = implode('&', $qs);
        if (!empty($qs)) {
            $url .= "?$qs";
        }

        $result = json_decode(self::$get_client->get($url));
        /* bd(compact('url', 'result')); */

        return $result;
    }



    public function ___send() {
        $email = $this->mail;
        /* bd(compact('this', 'email')); */
        return $this->sendEmail($email);
    }



    public function stringifyEmailAndNameArray(array $addresses) {
        $list = [];
        foreach ($addresses as $email => $name) {
            $email = filter_var($email, FILTER_VALIDATE_EMAIL);
            if (!$email) continue;
            if (!empty($name) && $name != $email) {
                $list[] = "$name <$email>";
            } else {
                $list[] = $email;
            }
        }
        return implode(", ", $list);
    }



    public function addCc(string $email, string $name = '') {
        if ($email = filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->mail['ccs'][$email] = $name;
            return true;
        }
        return false;
    }



    public function addBcc(string $email, string $name = '') {
        if ($email = filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->mail['bccs'][$email] = $name;
            return true;
        }
        return false;
    }



    public function Ccs(array $addresses) {
        $addresses = array_filter(
            $addresses,
            function ($email) { return false !== filter_var($email, FILTER_VALIDATE_EMAIL); },
            ARRAY_FILTER_USE_KEY
        );
        $this['Ccs'] = $addresses;
        return $this;
    }



    public function Bccs(array $addresses) {
        $addresses = array_filter(
            $addresses,
            function ($email) { return false !== filter_var($email, FILTER_VALIDATE_EMAIL); },
            ARRAY_FILTER_USE_KEY
        );
        $this['bccs'] = $addresses;
        return $this;
    }



    public function getCcs() {
        return $this->stringifyEmailAndNameArray($this->mail['ccs']);
    }



    public function getBccs() {
        return $this->stringifyEmailAndNameArray($this->mail['bccs']);
    }



    public function asArray() {
        return $this->mail;
    }



    public function sendEmail(array $email, bool $debug = false) {
        $token       = $this->server_token;
        self::initClients($token);

        $sig         = $this->sender_signature;
        $track_html  = in_array('htmllinks',  $this->track_flags);
        $track_plain = in_array('plainlinks', $this->track_flags);
        $track_opens = in_array('open',       $this->track_flags);
        $send_count  = 0;

        if ($track_plain && $track_html) {
            $track_links = 'HtmlAndText';
        } elseif ($track_plain) {
            $track_links = 'TextOnly';
        } elseif ($track_html) {
            $track_links = 'HtmlOnly';
        } else {
            $track_links = 'None';
        }

        $ccs  = $this->stringifyEmailAndNameArray($email['ccs']);
        $bccs = $this->stringifyEmailAndNameArray($email['bccs']);

        if ($debug) {
            bd(compact('this', 'email', 'token', 'sig'));
        }

        if (!empty($token) && !empty($sig)) {
            $send_count = 0;
            try {
                $tos = array_fill_keys(array_keys($email['to']), '');
                $tos = array_merge($tos, $email['toName']);
                $to_list = $this->stringifyEmailAndNameArray($tos);
                /* bd(compact('tos', 'to_list')); */

                $payload = [
                    'From'          => $sig,
                    'To'            => $to_list,
                    'Subject'       => $email['subject'],
                    'Tag'           => $email['tag'] ?? '',
                    'HtmlBody'      => $email['bodyHTML'] ?? '',
                    'TextBody'      => $email['body'] ?? '',
                    'TrackOpens'    => $track_opens,
                    'TrackLinks'    => $track_links,
                    'MessageStream' => $email['stream'] ?? 'outbound',
                ];

                $ccs  = $this->getCcs();
                $bccs = $this->getBccs();
                if (!empty($bccs)) $payload['Bcc'] = $bccs;
                if (!empty($ccs))  $payload['Cc']  = $ccs;

                if (!empty($email['meta'])) {
                    $filtered_meta = [];
                    foreach ($email['meta'] as $k => $v) {
                        $k = $this->sanitizeHeaderValue($k);
                        $v = $this->sanitizeHeaderValue($v);
                        $filtered_meta[$k] = $v;
                    }
                    if (!empty($filtered_meta)) {
                        $payload['Metadata'] = $filtered_meta;
                    }
                }

                $attachments = [];
                foreach ($email['attachments'] as $filename => $filelocation) {
                    $content = file_get_contents($filelocation);
                    if (false === $content) continue;
                    $attachments[] = [
                        'Name' => $filename,
                        'Content' => base64_encode($content),
                        'ContentType' => mime_content_type($filelocation),
                    ];
                }

                if (!empty($attachments)) {
                    $payload['Attachments'] = $attachments;
                }

                if (!empty($email['replyTo'])) {
                    $payload['ReplyTo'] = $email['replyTo'];
                    if (!empty($email['replyToName'])) {
                        $payload['ReplyTo'] = $email['replyToName'] . " <{$email['replyTo']}>";
                    }
                }

                $send_result = json_decode(
                    self::$post_client->post(
                        'https://api.postmarkapp.com/email',
                        json_encode($payload)
                    )
                );

                $error_code = $send_result->ErrorCode;
                if ($error_code) {
                    $logmsg = $send_result->Message ?? "Error sending email";
                } else {
                    $send_count = 1;
                    $this->email_id = $send_result->MessageID;
                    $this->mail['send_results'][] = $send_result;
                    $logmsg = "Email accepted with message ID: {$send_result->MessageID}";
                }

                $this->vlog($logmsg);
            }

            catch (\Throwable $e) {
                $this->vlog($e->getMessage());
                throw $e;
            }
        } else {
            $this->vlog("Required settings not set.");
        }

        return $send_count;
    }



    protected function varToString($var = null) {
        ob_start();
        var_dump($var);
        $string = ob_get_contents();
        ob_end_clean();
        return $string;
    }



    protected function vlog($msg) {
        if (!is_string($msg)) $msg = $this->varToString($msg);
        $this->log($msg);
    }



    public function getModuleConfigInputfields(InputfieldWrapper $fields) {
        $modules  = wire()->modules;

        $service_status = $this->getPostmarkServiceStatus();
        /* $service_status->status = 'DELAYED'; */
        $postmark_is_limited = in_array($service_status->status, ['DOWN', 'MAINTENANCE', 'DEGRADED']);

        $f = $modules->get('InputfieldMarkup');
        $status = $service_status->status;
        $status_class = wire()->sanitizer->snakeCase($status);
        $f->label = $this->_("Postmark Status");
        $f->value = "<span>Postmark is <span class='postmark-status-$status_class' style='font-weight:600'>$status</span></span>";
        $f->notes = $this->_("See the Postmark [status page](https://status.postmarkapp.com/) for full stats and delivery timings.");
        $fields->add($f);

        $info = new \stdClass();
        $server_info = new \stdClass();

        if ($postmark_is_limited) {
            $error = true;
            $info->Message = 'Postmark stats not available due to service status';
        } else {
            if (!empty($this->server_token)) {
                $server_info = $this->getServerInfo();
                /* bd($server_info); */
                $n_days  = 30;
                $from_ts = strtotime("now -$n_days days");
                $tag     = '';
                $info    = $this->getServerSendStats($tag, $from_ts);
                $error   = !empty($info->ErrorCode);
            } else {
                $error = true;
                $info->Message = 'Invalid server token';
            }
        }

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'server_token');
        $f->attr('value', $this->server_token);
        $f->required = true;
        $f->icon = 'key';
        $f->label = $this->_('Your Postmark Server Token');
        $f->notes = $this->_('Create one fron your [Postmark Servers](https://account.postmarkapp.com/servers) page > then pick your server and hit the "API Tokens" tab.');
        if (!$error) {
            $f->collapsed = Inputfield::collapsedPopulated;
        }
        $f->pattern = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
        $fields->add($f);

        $f = $modules->get('InputfieldMarkup');
        if ($error) {
            $f->label = __('Error');
            $msg = $info->Message;
            $f->value = "<p>$msg</p>";
        } else {
            $server_title = $server_info->Name;
            $server_type  = $server_info->DeliveryType;
            $server_link  = $server_info->ServerLink;
            $f->label = $this->_("$n_days Day Stats for $server_type Server: '$server_title'");
            $keys = [
                'Sent'               => 'Sent',
                'Opens'              => 'Opened',
                'UniqueOpens'        => 'Unique Opens',
                'TotalClicks'        => 'Total Clicks',
                'Bounced'            => 'Bounced',
                'BounceRate'         => 'Bounce Rate',
                'SpamComplaints'     => 'Spam Complaints',
                'SpamComplaintsRate' => 'Spam Complaints Rate',
            ];
            $msg = '';
            foreach ($keys as $key => $fieldname) {
                $value = $info->$key;
                if (false !== strpos($key, 'Rate')) {
                    $value .= "%";
                }
                $msg .= "<td class=''>$value</td> ";
            }
            $info = var_export($info, true);
            $f->value = "
                <table class='AdminDataTable AdminDataList AdminDataTableResponsive'>
                <thead><tr role=row>
                <th>Sent</th>
                <th>Opened</th>
                <th>Unique Opens</th>
                <th>Total Clicks</th>
                <th>Bounced</th>
                <th>Bounce Rate</th>
                <th>Spam Complaints</th>
                <th>Spam Complaint Rate</th>
                </tr></thead>
                <tbody><tr>
                $msg
                </tr></tbody>
                </table>
                ";
            $f->notes = $this->_("Visit [your server's page]($server_link) on Postmark.");
        }
        $fields->add($f);

        $f = $modules->get('InputfieldEmail');
        $f->attr('name', 'sender_signature');
        $f->attr('value', $this->sender_signature);
        $f->required = true;
        $f->label = $this->_('Sender Signature');
        $f->icon = 'envelope';
        $f->notes = $this->_(
            'See your [Postmark Sender Signatures](https://account.postmarkapp.com/signature_domains) page.
             Any email you send via Postmark will use this email address. You can specify a different **reply-to** address if you need to.
            ');
        $f->description = $this->_(
            "Please enter any one of the sender signatures you have registered against your postmark account.
            ");
        $f->columnWidth = 50;
        if (!$postmark_is_limited && $error) {
            $f->collapsed = Inputfield::collapsedPopulated;
        }
        $fields->add($f);

        $f = $modules->get('InputfieldCheckboxes');
        $f->attr('name', 'track_flags');
        $f->attr('value', $this->track_flags);
        $f->label = $this->_('Do you want email tracking?');
        $f->description = _("Choose which types of tracking you'd like.");
        $track_options = [
            'open'       => $this->_('Email opened'),
            'plainlinks' => $this->_('Plain body link clicks'),
            'htmllinks'  => $this->_('HTML body link clicks'),
        ];
        foreach ($track_options as $k => $string) {
            $f->addOption($k, $string);
        }
        if (!$postmark_is_limited && $error) {
            $f->collapsed = Inputfield::collapsedPopulated;
        }
        $f->columnWidth = 50;
        $fields->add($f);

        return $fields;
    }



    public function sendTestMessage() {
        $email             = self::blankEmail();
        $now               = time();
        $email->to         = $this->sender_signature;
        $email['from']     = $this->sender_signature;
        $email['fromName'] = 'WireMailPostmark';
        $email['subject']  = 'Test email from WiremailPostmark: ' . $now;
        $email['body']     = 'This is the plain-text test email body.';
        $email['bodyHTML'] = "<p>This is the <abbr title='Hypertext Markup Language'>HTML</abbr> test email body.</p>";
        $email['tag']      = 'test-send';

        $result = $this->sendEmail($email, true);
        return $result;
    }
}
