<?php

namespace Oro\Bundle\ImapBundle\Mail\Storage;

use Zend\Mail\Storage\Folder as BaseFolder;

class Folder extends BaseFolder
{
    const FLAG_SENT   = 'Sent';
    const FLAG_SPAM   = 'Spam';
    const FLAG_TRASH  = 'Trash';
    const FLAG_DRAFTS = 'Drafts';
    const FLAG_INBOX  = 'Inbox';
    const FLAG_ALL    = 'All';

    /** @var string[] */
    public $flags = null;

    /**
     * Determines whether this folder is marked by the given flag
     *
     * @param string|array $flags one flag or an array with multiple flags
     *
     * @return bool
     */
    public function hasFlag($flags)
    {
        if (empty($this->flags)) {
            return false;
        }

        if (false == is_array($flags)) {
            $flags = [$flags];
        }

        $flags = array_map(
            function ($item) {
                if (false === strpos($item, '\\')) {
                    $item = '\\' . $item;
                }
                return $item;
            },
            $flags
        );

        if (count($flags) > 1) {
            return count(array_intersect($this->flags, $flags)) > 0;
        } else {
            return in_array($flags[0], $this->flags);
        }
    }

    /**
     * Sets flags
     *
     * @param string[] $flags
     */
    public function setFlags(array $flags)
    {
        if ($this->flags === null) {
            $this->flags = $flags;
        } else {
            foreach ($flags as $flag) {
                if (!in_array($flag, $this->flags)) {
                    $this->flags[] = $flag;
                }
            }
        }
    }

    /**
     * Adds a flag
     *
     * @param string $flag
     */
    public function addFlag($flag)
    {
        if ($this->flags === null) {
            $this->flags = array();
        }
        if (!(strpos($flag, '\\') === 0)) {
            $flag = '\\' . $flag;
        }
        if (!in_array($flag, $this->flags)) {
            $this->flags[] = $flag;
        }
    }

    /**
     * Deletes a flag
     *
     * @param string $flag
     */
    public function deleteFlag($flag)
    {
        if ($this->flags !== null) {
            if (!(strpos($flag, '\\') === 0)) {
                $flag = '\\' . $flag;
            }
            unset($this->flags[$flag]);
        }
    }
}
