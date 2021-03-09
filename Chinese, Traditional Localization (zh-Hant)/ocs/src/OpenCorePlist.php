<?php

class OpenCorePlist extends CFPropertyList\CFPropertyList {
    function __construct(string $filename) {
        if(!file_exists($filename)) {
            throw new \Exception("File not found - ".getcwd()." $filename ");
        }
        parent::__construct($filename, CFPropertyList\CFPropertyList::FORMAT_XML);
    }

    function applyRules(Rules $rules) {
        $seen_title = [];
        // Check for missing groups
        foreach(array_keys(array_diff_key($rules->rule, $this->toArray())) as $missing_group) {
            $this->print_msg("-<span class=\"warning\">$missing_group</span> -->缺少此組內容");
        }
        // Check for missing sections
        $confArray = $this->toArray();
        foreach($rules->rule as $group=>$block) {
            foreach($block as $k=>$v) {
                if($k == 'top' || $k[1]==':') continue;
                if(!array_key_exists(trim($k,':'), $confArray[$group])) {
                    $this->print_msg("!<b>$group</b> - <b>".trim($k,':')."</b> -->缺少此部分");
                }
            }
        }
        foreach($this->toArray() as $group=>$d) {
            if (!array_key_exists($group, $rules->rule)) {
                $this->print_msg("-<span class=\"warning\">$group</span> -->這些內容都不需要");
                continue;
            }
            echo "\n<br/><div class=\"group\" style=\"text-align:center\">$group</div><br/>\n";
            if(!empty($rules->rule[$group]["top"])) {
                foreach($rules->rule[$group]["top"] as $rule) {
                    if(!empty($rule)) {
                        $msgs = $rule->exec($d, $this->value[0]->{$group});
                        if($rule->title && empty($seen_title[$rule->title])) {
                            echo "\n<br/><span class=\"section\">".$rule->title."</span><br/>\n";
                            $seen_title[$rule->title] = true;
                        }
                        foreach($msgs as $k=>$msg) {
                            if(!empty($msg) && $msg!='""') {
                                $this->print_msg($msg);
                                // Make sure we don't match more rules to the same entry
                                if(is_int($k) && $k) {
                                    unset($d[$k-1]);
                                }
                                // @phan-suppress-next-line PhanTypeArraySuspicious
                                else if($k[0]==':') {
                                    // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
                                    [,$sec,$tk] = explode(':',$k,3);
                                    unset($d[$sec][$tk]);
                                }
                                else if($k) unset($d[$k]);
                            }
                        }
                    }
                }
            }

            foreach($d as $section=>$dd) {
                if(empty($seen_title[$section])) {
                    echo "<br/><span class=\"section\">$section</span><br/>";
                    $seen_title[$section] = true;
                }
                if(!empty($rules->rule[$group][":$section"])) {
                    foreach($rules->rule[$group][":$section"] as $rule) {
                        if(!empty($rule)) {
                            $msgs = $rule->exec($dd, $this->value[0]->{$group}->{$section});
                            foreach($msgs as $k=>$msg) {
                                if(!empty($msg) && $msg!='""') {
                                    $this->print_msg($msg);
                                    if(is_int($k) && $k) unset($dd[$k-1]);  // Make sure we don't match more rules to the same entry
                                    else if($k) unset($dd[$k]);
                                }
                            }
                        }
                    }
                }
                // Check if there are rules hiding in a sub-section
                if(is_array($dd)) {
                    foreach($dd as $ssection=>$ddd) {
                        if(!empty($rules->rule[$group]["::$ssection"])) {
                            foreach($rules->rule[$group]["::$ssection"] as $rule) {
                                if(!empty($rule)) {
                                    $msgs = $rule->exec($ddd, $this->value[0]->{$group}->{$section}->{$ssection}, false);
                                    foreach($msgs as $k=>$msg) {
                                        if(!empty($msg) && $msg!='""') {
                                            $this->print_msg($msg);
                                            if(is_int($k) && $k) unset($ddd[$k-1]);  // Make sure we don't match more rules to the same entry
                                            else if($k) unset($ddd[$k]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            echo "<hr noshade=\"noshade\" />\n";
            $seen_title = [];
        }
    }

    function print_msg($msg) {
        $msg = trim($msg,'"');
        switch($msg[0]) {
            case ' ': $sev = 'good'; $icon = 'check-circle'; $msg = substr($msg,1); break;
            case '-': $sev = 'warn'; $icon = 'question-circle'; $msg = substr($msg,1); break;
            case '!': $sev = 'err'; $icon = 'times-circle'; $msg = substr($msg,1); break;
            case '%': $sev = 'info'; $icon = 'info-circle'; $msg = substr($msg,1); break;
            default: $sev = 'good'; $icon = 'check-circle'; break;
        }
        if(preg_match('/{\$([^}]+)}/', $msg, $match)) {
            echo "<span class=\"err\"> -->這裡缺少了<b>{$match[1]}</b></span><br/>\n";
        } else {
            echo "<span class=\"{$sev}\">$msg</span><br/>\n";  // Markdown-extra to add the severity class - see main.css
        }
    }
}
