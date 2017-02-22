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

namespace SearchDAV\Test;


use SearchDAV\DAV\QueryParser;
use SearchDAV\XML\BasicSearch;
use SearchDAV\XML\Literal;
use SearchDAV\XML\Operator;
use SearchDAV\XML\Order;
use SearchDAV\XML\Scope;


class QueryParserTest extends \PHPUnit_Framework_TestCase {
	public function testParseBasicQuery() {
		$query = file_get_contents(__DIR__ . '/basicquery.xml');
		$parser = new QueryParser();
		$xml = $parser->parse($query, null, $rootElementName);

		$this->assertEquals('{DAV:}searchrequest', $rootElementName);
		$this->assertArrayHasKey('{DAV:}basicsearch', $xml);

		/** @var BasicSearch $search */
		$search = $xml['{DAV:}basicsearch'];
		$this->assertInstanceOf(BasicSearch::class, $search);

		$this->assertEquals(['{DAV:}getcontentlength'], $search->select);
		$this->assertEquals([
			new Scope('/container1/', 'infinity')
		], $search->from);
		$this->assertEquals(new Operator(Operator::OPERATION_GREATER_THAN, [
			'{DAV:}getcontentlength',
			new Literal(10000)
		]), $search->where);
		$this->assertEquals([
			new Order('{DAV:}getcontentlength', Order::ASC)
		], $search->orderBy);
	}

	public function testParseNoOrder() {
		$query = file_get_contents(__DIR__ . '/noorder.xml');
		$parser = new QueryParser();
		$xml = $parser->parse($query, null, $rootElementName);

		$this->assertEquals('{DAV:}searchrequest', $rootElementName);
		$this->assertArrayHasKey('{DAV:}basicsearch', $xml);

		/** @var BasicSearch $search */
		$search = $xml['{DAV:}basicsearch'];
		$this->assertInstanceOf(BasicSearch::class, $search);

		$this->assertEquals(['{DAV:}getcontentlength'], $search->select);
		$this->assertEquals([
			new Scope('/container1/', 'infinity'),
			new Scope('/container2/', 1),
		], $search->from);
		$this->assertEquals(new Operator(Operator::OPERATION_IS_COLLECTION, []), $search->where);
		$this->assertEquals([], $search->orderBy);
	}

	/**
	 * @expectedException \Sabre\XML\ParseException
	 */
	public function testParseNoFrom() {
		$query = file_get_contents(__DIR__ . '/nofrom.xml');
		$parser = new QueryParser();
		$parser->parse($query, null, $rootElementName);
	}
}
