<?php

namespace Oro\Bundle\ImapBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\ImapBundle\Entity\ImapEmail;

class ImapEmailRepository extends EntityRepository
{
    /**
     * @param EmailFolder $folder
     * @param int[]       $uids
     *
     * @return QueryBuilder
     */
    public function getEmailsByUidsQueryBuilder(EmailFolder $folder, array $uids)
    {
        return $this->createQueryBuilder('imap_email')
            ->innerJoin('imap_email.email', 'email')
            ->innerJoin('email.emailUsers', 'email_users')
            ->innerJoin('email_users.folder', 'folder')
            ->where('folder = :folder AND imap_email.uid IN (:uids)')
            ->setParameter('folder', $folder)
            ->setParameter('uids', $uids);
    }

    /**
     * @param EmailFolder $folder
     * @param int[]       $uids
     *
     * @return int[] Existing UIDs
     */
    public function getExistingUids(EmailFolder $folder, array $uids)
    {
        $rows = $this->getEmailsByUidsQueryBuilder($folder, $uids)
            ->select('imap_email.uid')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row['uid'];
        }

        return $result;
    }

    /**
     * @param EmailOrigin $origin
     * @param string[]    $messageIds
     *
     * @return QueryBuilder
     */
    public function getEmailsByMessageIdsQueryBuilder(EmailOrigin $origin, array $messageIds)
    {
        return $this->createQueryBuilder('imap_email')
            ->innerJoin('imap_email.imapFolder', 'imap_folder')
            ->innerJoin('imap_email.email', 'email')
            ->innerJoin('email.emailUsers', 'email_users')
            ->innerJoin('email_users.folder', 'folder')
            ->where('folder.origin = :origin AND email.messageId IN (:messageIds)')
            ->setParameter('origin', $origin)
            ->setParameter('messageIds', $messageIds);
    }

    /**
     * @param EmailOrigin $origin
     * @param string[]    $messageIds
     *
     * @return ImapEmail[] Existing emails
     */
    public function getEmailsByMessageIds(EmailOrigin $origin, array $messageIds)
    {
        $rows = $this->getEmailsByMessageIdsQueryBuilder($origin, $messageIds)
            ->select('imap_email, email, email_users, imap_folder, folder')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @param EmailOrigin $origin
     * @param string[]    $messageIds
     *
     * @return ImapEmail[] Existing emails
     */
    public function getOutdatedEmailsByMessageIds(EmailOrigin $origin, array $messageIds)
    {
        $rows = $this->getEmailsByMessageIdsQueryBuilder($origin, $messageIds)
            ->select('imap_email, email, email_users, imap_folder, folder')
            ->andWhere('folder.outdatedAt IS NOT NULL')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @param integer $folder - id of Folder
     * @param integer $email  - id of Email
     *
     * @return integer|false
     */
    public function getUid($folder, $email)
    {
        $query = $this->createQueryBuilder('e')
            ->select('e.uid')
            ->innerJoin('e.imapFolder', 'if')
            ->where('e.email = :email AND if.folder = :folder')
            ->setParameter('email', $email)
            ->setParameter('folder', $folder)
            ->getQuery();

        return $query->getSingleScalarResult();
    }
}
