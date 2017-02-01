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

namespace SearchDAV\XML;

use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

class BasicSearch implements XmlDeserializable {
	/**
	 * @var string[]
	 *
	 * The list of properties to be selected in clark notation
	 */
	public $select;
	/**
	 * @var Scope[]
	 *
	 * The collections to perform the search in
	 */
	public $from;
	/**
	 * @var Operator[]
	 */
	public $where;
	/**
	 * @var Order[]
	 */
	public $orderBy;

	static function xmlDeserialize(Reader $reader) {
		$search = new self();

		$elements = \Sabre\Xml\Deserializer\keyValue($reader);
		$search->select = isset($elements['{DAV:}select']) ? $elements['{DAV:}select'] : null;
		$search->from = isset($elements['{DAV:}from']) ? $elements['{DAV:}from'] : null;
		$search->where = isset($elements['{DAV:}where']) ? $elements['{DAV:}where'] : null;
		$search->orderBy = isset($elements['{DAV:}orderby']) ? $elements['{DAV:}orderby'] : null;

		return $search;
	}
}
