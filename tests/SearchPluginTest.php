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


use Sabre\DAV\FS\Directory;
use Sabre\DAV\Server;
use Sabre\DAV\Xml\Service;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Sabre\VObject\Parser\XML;
use SearchDAV\Backend\ISearchBackend;
use SearchDAV\Backend\SearchPropertyDefinition;
use SearchDAV\Backend\SearchResult;
use SearchDAV\DAV\SearchPlugin;
use SearchDAV\XML\BasicSearch;
use SearchDAV\XML\Literal;
use SearchDAV\XML\Operator;
use SearchDAV\XML\Order;
use SearchDAV\XML\Scope;

class SearchPluginTest extends \PHPUnit_Framework_TestCase {
	/** @var ISearchBackend|\PHPUnit_Framework_MockObject_MockObject */
	private $searchBackend;

	protected function setUp() {
		parent::setUp();

		$this->searchBackend = $this->getMockBuilder(ISearchBackend::class)
			->getMock();
	}

	public function testHandleParseException() {
		$this->searchBackend->expects($this->any())
			->method('getArbiterPath')
			->willReturn('foo');

		$request = new Request('SEARCH', 'foo', [
			'Content-Type' => 'text/xml'
		], fopen(__DIR__ . '/nofrom.xml', 'r'));
		$response = new Response();

		$plugin = new SearchPlugin($this->searchBackend);

		$plugin->searchHandler($request, $response);

		$this->assertEquals(400, $response->getStatus());
	}

	public function testHTTPMethods() {
		$this->searchBackend->expects($this->any())
			->method('getArbiterPath')
			->willReturn('foo');

		$plugin = new SearchPlugin($this->searchBackend);
		$server = new Server();
		$server->setBaseUri('/index.php');
		$plugin->initialize($server);

		$this->assertEquals([], $plugin->getHTTPMethods('bar'));

		$this->assertEquals(['SEARCH'], $plugin->getHTTPMethods('foo'));

		$this->assertEquals([], $plugin->getHTTPMethods('http://example.com/index.php/bar'));

		$this->assertEquals(['SEARCH'], $plugin->getHTTPMethods('http://example.com/index.php/foo'));
	}

	public function testOptionHandler() {
		$this->searchBackend->expects($this->any())
			->method('getArbiterPath')
			->willReturn('foo');

		$plugin = new SearchPlugin($this->searchBackend);

		$request = new Request('OPTIONS', '/index.php/bar');
		$request->setBaseUrl('/index.php');
		$response = new Response();

		$plugin->optionHandler($request, $response);

		$this->assertEquals(false, $response->hasHeader('DASL'));

		$request = new Request('OPTIONS', '/index.php/foo');
		$request->setBaseUrl('/index.php');
		$response = new Response();

		$plugin->optionHandler($request, $response);

		$this->assertEquals(true, $response->hasHeader('DASL'));
	}

	public function testSchemaDiscovery() {
		$this->searchBackend->expects($this->any())
			->method('getArbiterPath')
			->willReturn('foo');

		$plugin = new SearchPlugin($this->searchBackend);
		$server = new Server();
		$plugin->initialize($server);

		$request = new Request('SEARCH', '/index.php/foo', [
			'Content-Type' => 'text/xml'
		]);
		$request->setBaseUrl('/index.php');
		$request->setBody(fopen(__DIR__ . '/discover.xml', 'r'));
		$response = new Response();

		$this->searchBackend->expects($this->once())
			->method('isValidScope')
			->willReturn(true);

		$this->searchBackend->expects($this->once())
			->method('getPropertyDefinitionsForScope')
			->willReturn([
				new SearchPropertyDefinition('{DAV:}getcontentlength', true, true, true, SearchPropertyDefinition::DATATYPE_NONNEGATIVE_INTEGER),
				new SearchPropertyDefinition('{DAV:}getcontenttype', true, true, true),
				new SearchPropertyDefinition('{DAV:}displayname', true, true, true),
				new SearchPropertyDefinition('{http://ns.nextcloud.com:}fileid', false, true, true, SearchPropertyDefinition::DATATYPE_NONNEGATIVE_INTEGER),
			]);

		$plugin->searchHandler($request, $response);

		$parser = new Service();
		$parsedResponse = $parser->parse($response->getBody());
		$expected = $parser->parse(fopen(__DIR__ . '/discoverresponse.xml', 'r'));
		$this->assertEquals($expected, $parsedResponse);
	}

	public function testSearchQuery() {
		$this->searchBackend->expects($this->any())
			->method('getArbiterPath')
			->willReturn('foo');

		$plugin = new SearchPlugin($this->searchBackend);
		$server = new Server();
		$plugin->initialize($server);

		$request = new Request('SEARCH', '/index.php/foo', [
			'Content-Type' => 'text/xml'
		]);
		$request->setBaseUrl('/index.php');
		$request->setBody(fopen(__DIR__ . '/basicquery.xml', 'r'));
		$response = new Response();

		$this->searchBackend->expects($this->any())
			->method('isValidScope')
			->willReturn(true);

		$query = new BasicSearch();
		$query->orderBy = [
			new Order('{DAV:}getcontentlength', Order::ASC)
		];
		$query->select = ['{DAV:}getcontentlength'];
		$query->from = [
			new Scope('/container1/', 'infinity', '/container1/')
		];
		$query->where = new Operator(Operator::OPERATION_GREATER_THAN, [
			'{DAV:}getcontentlength',
			new Literal(10000)
		]);

		$this->searchBackend->expects($this->once())
			->method('search')
			->with($query)
			->willReturn([
				new SearchResult(
					new Directory('/foo'),
					'/foo'
				)
			]);

		$plugin->searchHandler($request, $response);

		$parser = new Service();
		$parsedResponse = $parser->parse($response->getBody());
		$expected = $parser->parse(fopen(__DIR__ . '/searchresult.xml', 'r'));
		$this->assertEquals($expected, $parsedResponse);
	}
}
