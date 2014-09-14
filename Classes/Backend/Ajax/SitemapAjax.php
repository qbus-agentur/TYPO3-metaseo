<?php
namespace Metaseo\Metaseo\Backend\Ajax;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Markus Blaschke <typo3@markus-blaschke.de> (metaseo)
 *  (c) 2013 Markus Blaschke (TEQneers GmbH & Co. KG) <blaschke@teqneers.de> (tq_seo)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Metaseo\Metaseo\Utility\DatabaseUtility;

/**
 * TYPO3 Backend ajax module sitemap
 *
 * @package     TYPO3
 * @subpackage  metaseo
 */
class SitemapAjax extends \Metaseo\Metaseo\Backend\Ajax\AbstractAjax {

    /**
     * Return sitemap entry list for root tree
     *
     * @return    array
     */
    protected function executeGetList() {
        // Init
        $rootPageList = \Metaseo\Metaseo\Utility\BackendUtility::getRootPageList();

        $rootPid      = (int)$this->postVar['pid'];
        $offset       = (int)$this->postVar['start'];
        $limit        = (int)$this->postVar['limit'];
        $itemsPerPage = (int)$this->postVar['pagingSize'];

        $searchFulltext      = trim((string)$this->postVar['criteriaFulltext']);
        $searchPageUid       = trim((int)$this->postVar['criteriaPageUid']);
        $searchPageLanguage  = trim((string)$this->postVar['criteriaPageLanguage']);
        $searchPageDepth     = trim((string)$this->postVar['criteriaPageDepth']);
        $searchIsBlacklisted = (bool)trim((string)$this->postVar['criteriaIsBlacklisted']);

        // ############################
        // Critera
        // ############################
        $where = array();

        // Root pid limit
        $where[] = 'page_rootpid = ' . (int)$rootPid;

        // Fulltext
        if (!empty($searchFulltext)) {
            $where[] = 'page_url LIKE ' . DatabaseUtility::quote('%' . $searchFulltext . '%', 'tx_metaseo_sitemap');
        }

        // Page id
        if (!empty($searchPageUid)) {
            $where[] = 'page_uid = ' . (int)$searchPageUid;
        }

        // Lannguage
        if ($searchPageLanguage != -1 && strlen($searchPageLanguage) >= 1) {
            $where[] = 'page_language = ' . (int)$searchPageLanguage;
        }

        // Depth
        if ($searchPageDepth != -1 && strlen($searchPageDepth) >= 1) {
            $where[] = 'page_depth = ' . (int)$searchPageDepth;
        }

        if ($searchIsBlacklisted) {
            $where[] = 'is_blacklisted = 1';
        }

        // Build where
        $where = '( ' . implode(' ) AND ( ', $where) . ' )';

        // ############################
        // Pager
        // ############################

        // Fetch total count of items with this filter settings
        $query = 'SELECT COUNT(*) as count
                    FROM tx_metaseo_sitemap
                   WHERE ' . $where;
        $itemCount = DatabaseUtility::getOne($query);

        // ############################
        // Sort
        // ############################
        // default sort
        $sort = 'page_depth ASC, page_uid ASC';

        if (!empty($this->sortField) && !empty($this->sortDir)) {
            // already filered
            $sort = $this->sortField . ' ' . $this->sortDir;
        }

        // ############################
        // Fetch sitemap
        // ############################
        $query = 'SELECT uid,
                         page_rootpid,
                         page_uid,
                         page_language,
                         page_url,
                         page_depth,
                         is_blacklisted,
                         FROM_UNIXTIME(tstamp) as tstamp,
                         FROM_UNIXTIME(crdate) as crdate
                    FROM tx_metaseo_sitemap
                   WHERE ' . $where . '
                ORDER BY ' . $sort . '
                   LIMIT ' . $offset . ', ' . $itemsPerPage;
        $list = DatabaseUtility::getAll($query);

        $ret = array(
            'results' => $itemCount,
            'rows'    => $list,
        );

        return $ret;
    }

    /*
     * Blacklist sitemap entries
     *
     * @return    boolean
     */
    protected function executeBlacklist() {
        $uidList = $this->postVar['uidList'];
        $rootPid = (int)$this->postVar['pid'];

        $uidList = $GLOBALS['TYPO3_DB']->cleanIntArray($uidList);

        if (empty($uidList) || empty($rootPid)) {
            return FALSE;
        }

        $where   = array();
        $where[] = 'page_rootpid = ' . (int)$rootPid;
        $where[] = DatabaseUtility::conditionIn('uid', $uidList);
        $where   = '( ' . implode(' ) AND ( ', $where) . ' )';

        $query = 'UPDATE tx_metaseo_sitemap
                     SET is_blacklisted = 1
                   WHERE ' . $where;
        DatabaseUtility::exec($query);

        return TRUE;
    }

    /*
     * Whitelist sitemap entries
     *
     * @return    boolean
     */
    protected function executeWhitelist() {
        $uidList = $this->postVar['uidList'];
        $rootPid = (int)$this->postVar['pid'];

        $uidList = $GLOBALS['TYPO3_DB']->cleanIntArray($uidList);

        if (empty($uidList) || empty($rootPid)) {
            return FALSE;
        }

        $where   = array();
        $where[] = 'page_rootpid = ' . (int)$rootPid;
        $where[] = DatabaseUtility::conditionIn('uid', $uidList);
        $where   = '( ' . implode(' ) AND ( ', $where) . ' )';

        $query = 'UPDATE tx_metaseo_sitemap
                     SET is_blacklisted = 0
                   WHERE ' . $where;
        DatabaseUtility::exec($query);

        return TRUE;
    }



    /**
     * Delete sitemap entries
     *
     * @return    boolean
     */
    protected function executeDelete() {
        $uidList = $this->postVar['uidList'];
        $rootPid = (int)$this->postVar['pid'];

        $uidList = $GLOBALS['TYPO3_DB']->cleanIntArray($uidList);

        if (empty($uidList) || empty($rootPid)) {
            return FALSE;
        }

        $where   = array();
        $where[] = 'page_rootpid = ' . (int)$rootPid;
        $where[] = DatabaseUtility::conditionIn('uid', $uidList);
        $where   = '( ' . implode(' ) AND ( ', $where) . ' )';

        $query = 'DELETE FROM tx_metaseo_sitemap
                         WHERE ' . $where;
        DatabaseUtility::exec($query);

        return TRUE;
    }

    /**
     * Delete all sitemap entries
     *
     * @return    boolean
     */
    protected function executeDeleteAll() {
        $rootPid = (int)$this->postVar['pid'];

        if (empty($rootPid) ) {
            return FALSE;
        }

        $where   = array();
        $where[] = 'page_rootpid = ' . (int)$rootPid;
        $where   = '( ' . implode(' ) AND ( ', $where) . ' )';

        $query = 'DELETE FROM tx_metaseo_sitemap
                         WHERE ' . $where;
        DatabaseUtility::exec($query);

        return TRUE;
    }

}
