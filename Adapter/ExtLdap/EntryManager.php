<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter\ExtLdap;

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\Exception\UpdateOperationException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 * @author Mike Klubertz <mike@klubertz.de>
 */
class EntryManager implements EntryManagerInterface
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function add(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_add($con, $entry->getDn(), $entry->getAttributes())) {
            throw new LdapException(sprintf('Could not add entry "%s": %s.', $entry->getDn(), ldap_error($con)));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_modify($con, $entry->getDn(), $entry->getAttributes())) {
            throw new LdapException(sprintf('Could not update entry "%s": %s.', $entry->getDn(), ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(Entry $entry)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_delete($con, $entry->getDn())) {
            throw new LdapException(sprintf('Could not remove entry "%s": %s.', $entry->getDn(), ldap_error($con)));
        }
    }

    /**
     * Adds values to an entry's multi-valued attribute from the LDAP server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function addAttributeValues(Entry $entry, string $attribute, array $values)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_mod_add($con, $entry->getDn(), [$attribute => $values])) {
            throw new LdapException(sprintf('Could not add values to entry "%s", attribute %s: %s.', $entry->getDn(), $attribute, ldap_error($con)));
        }
    }

    /**
     * Removes values from an entry's multi-valued attribute from the LDAP server.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function removeAttributeValues(Entry $entry, string $attribute, array $values)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_mod_del($con, $entry->getDn(), [$attribute => $values])) {
            throw new LdapException(sprintf('Could not remove values from entry "%s", attribute %s: %s.', $entry->getDn(), $attribute, ldap_error($con)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(Entry $entry, $newRdn, $removeOldRdn = true)
    {
        $con = $this->getConnectionResource();

        if (!@ldap_rename($con, $entry->getDn(), $newRdn, null, $removeOldRdn)) {
            throw new LdapException(sprintf('Could not rename entry "%s" to "%s": %s.', $entry->getDn(), $newRdn, ldap_error($con)));
        }
    }

    /**
     * Get the connection resource, but first check if the connection is bound.
     */
    private function getConnectionResource()
    {
        // If the connection is not bound, throw an exception. Users should use an explicit bind call first.
        if (!$this->connection->isBound()) {
            throw new NotBoundException('Query execution is not possible without binding the connection first.');
        }

        return $this->connection->getResource();
    }

    /**
     * @param iterable|UpdateOperation[] $operations An array or iterable of UpdateOperation instances
     *
     * @throws UpdateOperationException in case of an error
     */
    public function applyOperations(string $dn, iterable $operations): void
    {
        $operationsMapped = [];
        foreach ($operations as $modification) {
            $operationsMapped[] = $modification->toArray();
        }

        if (!@ldap_modify_batch($this->getConnectionResource(), $dn, $operationsMapped)) {
            throw new UpdateOperationException(sprintf('Error executing UpdateOperation on "%s": "%s".', $dn, ldap_error($this->getConnectionResource())));
        }

        
    }
    
    /**
     * Get the last ldap error
     */
    public function getLastLdapError(): string {
        
        return ldap_error($this->getConnectionResource());
        
    }
    
    /**
     * Get the last ldap error details
     */
    public function getLastLdapErrorDetails(): string {
        
        ldap_get_option($this->getConnectionResource(), LDAP_OPT_DIAGNOSTIC_MESSAGE, $err);
        
        return $err;
    }
}
