<?php

namespace Oro\Bundle\ImapBundle\Manager;

use Zend\Mail\Headers;
use Zend\Mail\Header\HeaderInterface;
use Zend\Mail\Header\AbstractAddressList;
use Zend\Mail\Address\AddressInterface;
use Zend\Mail\Storage\Exception as MailException;

use Oro\Bundle\ImapBundle\Connector\ImapConnector;
use Oro\Bundle\ImapBundle\Connector\Search\SearchQuery;
use Oro\Bundle\ImapBundle\Connector\Search\SearchQueryBuilder;
use Oro\Bundle\ImapBundle\Manager\DTO\ItemId;
use Oro\Bundle\ImapBundle\Manager\DTO\Email;
use Oro\Bundle\ImapBundle\Mail\Storage\Folder;
use Oro\Bundle\ImapBundle\Mail\Storage\Message;
use Oro\Bundle\ImapBundle\Util\DateTimeParser;

/**
 * Class ImapEmailManager
 *
 * @package Oro\Bundle\ImapBundle\Manager
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ImapEmailManager
{
    /** @var ImapConnector */
    protected $connector;

    /**
     * @param ImapConnector $connector
     */
    public function __construct(ImapConnector $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Checks if IMAP server supports the given capability
     *
     * @param string $capability
     *
     * @return bool
     */
    public function hasCapability($capability)
    {
        return in_array($capability, $this->connector->getCapability());
    }

    /**
     * Get selected folder
     *
     * @return string
     */
    public function getSelectedFolder()
    {
        return $this->connector->getSelectedFolder();
    }

    /**
     * Set selected folder
     *
     * @param string $folder
     */
    public function selectFolder($folder)
    {
        $this->connector->selectFolder($folder);
    }

    /**
     * Gets UIDVALIDITY of currently selected folder
     *
     * @return int
     */
    public function getUidValidity()
    {
        return $this->connector->getUidValidity();
    }

    /**
     * Gets the search query builder
     *
     * @return SearchQueryBuilder
     */
    public function getSearchQueryBuilder()
    {
        return $this->connector->getSearchQueryBuilder();
    }

    /**
     * Retrieve folders
     *
     * @param string|null $parentFolder The global name of a parent folder.
     * @param bool        $recursive    True to get all subordinate folders
     *
     * @return Folder[]
     */
    public function getFolders($parentFolder = null, $recursive = false)
    {
        return $this->connector->findFolders($parentFolder, $recursive);
    }

    /**
     * Retrieve emails by the given criteria
     *
     * @param SearchQuery $query
     *
     * @return ImapEmailIterator
     */
    public function getEmails(SearchQuery $query = null)
    {
        return new ImapEmailIterator(
            $this->connector->findItems($query),
            $this
        );
    }

    /**
     * Retrieve email by its UID
     *
     * @param int $uid The UID of an email message
     *
     * @return Email|null An Email DTO or null if an email with the given UID was not found
     */
    public function findEmail($uid)
    {
        try {
            $msg = $this->connector->getItem($uid);

            return $this->convertToEmail($msg);
        } catch (MailException\InvalidArgumentException $ex) {
            return null;
        }
    }

    /**
     * Creates Email DTO for the given email message
     *
     * @param Message $msg
     *
     * @return Email
     *
     * @throws \RuntimeException if the given message cannot be converted to {@see Email} object
     */
    public function convertToEmail(Message $msg)
    {
        $headers = $msg->getHeaders();
        $flags = $msg->getFlags();
        $email = new Email($msg, $flags);
        try {
            $email
                ->setId(
                    new ItemId(
                        intval($headers->get('UID')->getFieldValue()),
                        $this->connector->getUidValidity()
                    )
                )
                ->setSubject($this->getString($headers, 'Subject'))
                ->setFrom($this->getString($headers, 'From'))
                ->setSentAt($this->getDateTime($headers, 'Date'))
                ->setReceivedAt($this->getReceivedAt($headers))
                ->setInternalDate($this->getDateTime($headers, 'InternalDate'))
                ->setImportance($this->getImportance($headers))
                ->setMessageId($this->getString($headers, 'Message-ID'))
                ->setRefs($this->getReferences($headers, 'References'))
                ->setXMessageId($this->getString($headers, 'X-GM-MSG-ID'))
                ->setXThreadId($this->getString($headers, 'X-GM-THR-ID'));
            foreach ($this->getRecipients($headers, 'To') as $val) {
                $email->addToRecipient($val);
            }
            foreach ($this->getRecipients($headers, 'Cc') as $val) {
                $email->addCcRecipient($val);
            }
            foreach ($this->getRecipients($headers, 'Bcc') as $val) {
                $email->addBccRecipient($val);
            }

            return $email;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot parse email message. Subject: %s. Error: %s',
                    $email->getSubject(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Gets a string representation of an email header
     *
     * @param Headers $headers
     * @param string  $name
     *
     * @return string
     *
     * @throws \RuntimeException if a value of the requested header cannot be converted to a string
     */
    protected function getString(Headers $headers, $name)
    {
        $header = $headers->get($name);
        if ($header === false) {
            return '';
        } elseif ($header instanceof \ArrayIterator) {
            $values = [];
            $header->rewind();
            while ($header->valid()) {
                $values[] = sprintf('"%s"', $header->current()->getFieldValue());
                $header->next();
            }
            throw new \RuntimeException(
                sprintf(
                    'It is expected that the header "%s" has a string value, '
                    . 'but several values are returned. Values: %s.',
                    $name,
                    implode(', ', $values)
                )
            );
        }

        return $header->getFieldValue();
    }

    /**
     * Gets a email references header
     *
     * @param Headers $headers
     * @param string  $name
     *
     * @return string|null
     */
    protected function getReferences(Headers $headers, $name)
    {
        $values = [];
        $header = $headers->get($name);
        if ($header === false) {
            return null;
        } elseif ($header instanceof \ArrayIterator) {
            $header->rewind();
            while ($header->valid()) {
                $values[] = sprintf('"%s"', $header->current()->getFieldValue());
                $header->next();
            }
        } else {
            $values[] = $header->getFieldValue();
        }

        return implode(' ', $values);
    }

    /**
     * Gets an email header as DateTime type
     *
     * @param Headers $headers
     * @param string  $name
     *
     * @return \DateTime
     */
    protected function getDateTime(Headers $headers, $name)
    {
        $val = $headers->get($name);
        if ($val instanceof HeaderInterface) {
            return $this->convertToDateTime($val->getFieldValue());
        }

        return new \DateTime('0001-01-01', new \DateTimeZone('UTC'));
    }

    /**
     * Gets DateTime when an email is received
     *
     * @param Headers $headers
     *
     * @return \DateTime
     */
    protected function getReceivedAt(Headers $headers)
    {
        $val = $headers->get('Received');
        $str = '';
        if ($val instanceof HeaderInterface) {
            $str = $val->getFieldValue();
        } elseif ($val instanceof \ArrayIterator) {
            $val->rewind();
            $str = $val->current()->getFieldValue();
        }

        $delim = strrpos($str, ';');
        if ($delim !== false) {
            $str = trim(preg_replace('@[\r\n]+@', '', substr($str, $delim + 1)));

            return $this->convertToDateTime($str);
        }

        return new \DateTime('0001-01-01', new \DateTimeZone('UTC'));
    }

    /**
     * Get an email recipients
     *
     * @param Headers $headers
     * @param string  $name
     *
     * @return string[]
     */
    protected function getRecipients(Headers $headers, $name)
    {
        $result = array();
        $val    = $headers->get($name);
        if ($val instanceof AbstractAddressList) {
            /** @var AddressInterface $addr */
            foreach ($val->getAddressList() as $addr) {
                $result[] = $addr->toString();
            }
        }

        return $result;
    }

    /**
     * Gets an email importance
     *
     * @param Headers $headers
     *
     * @return integer
     */
    protected function getImportance(Headers $headers)
    {
        $importance = $headers->get('Importance');
        if ($importance instanceof HeaderInterface) {
            switch (strtolower($importance->getFieldValue())) {
                case 'high':
                    return 1;
                case 'low':
                    return -1;
                default:
                    return 0;
            }
        }

        $labels = $headers->get('X-GM-LABELS');
        if ($labels instanceof HeaderInterface) {
            if ($labels->getFieldValue() === '\\\\Important') {
                return 1;
            }
        } elseif ($labels instanceof \ArrayIterator) {
            foreach ($labels as $label) {
                if ($label instanceof HeaderInterface && $label->getFieldValue() === '\\\\Important') {
                    return 1;
                }
            }
        }

        return 0;
    }

    /**
     * Convert a string to DateTime
     *
     * @param string $value
     *
     * @return \DateTime
     *
     * @throws \Exception
     */
    protected function convertToDateTime($value)
    {
        return DateTimeParser::parse($value);
    }
}
