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

use PHPUnit\Framework\TestCase;
use Sabre\DAV\Server;
use SearchDAV\DAV\PathHelper;

class PathHelperTest extends TestCase {
	public function uriProvider() {
		return [
			['/', '', ''],
			['/index.php/', 'foo', 'foo'],
			['/index.php/', 'http://example.com/index.php/foo', 'foo'],
			['/index.php/', 'http://example.com/foo', null]
		];
	}

	/**
	 * @dataProvider  uriProvider
	 *
	 * @param $baseUri
	 * @param $uri
	 * @param $expected
	 */
	public function testGetPathFromUri($baseUri, $uri, $expected) {
		$server = new Server();
		$server->setBaseUri($baseUri);
		$pathHelper = new PathHelper($server);

		$this->assertEquals($expected, $pathHelper->getPathFromUri($uri));
	}
}
