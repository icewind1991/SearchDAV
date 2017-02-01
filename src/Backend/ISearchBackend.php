<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace SearchDAV\Backend;

use Sabre\DAV\INode;
use SearchDAV\XML\BasicSearch;

interface ISearchBackend {
	/**
	 * Get the path of the search arbiter of this backend
	 *
	 * The search arbiter is the URI that the client will send it's SEARCH requests to
	 * Note that this is not required to be the same as the search scopes which determine what to search in
	 *
	 * @return string
	 */
	public function getArbiterPath();

	/**
	 * Whether or not the search backend supports search requests on this scope
	 *
	 * @param string $href
	 * @param string|integer $depth 0, 1 or 'inifinite'
	 * @return bool
	 */
	public function isValidScope($href, $depth);

	/**
	 * List the available properties that can be used in search
	 *
	 * @return SearchPropertyDefinition[]
	 */
	public function getPropertyDefinitionsForScope($href);

	/**
	 * @param INode $searchNode the DAV node that the search request was made to
	 * @param BasicSearch $query
	 * @return SearchResult[]
	 */
	public function search(INode $searchNode, BasicSearch $query);
}
