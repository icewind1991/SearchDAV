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

namespace SearchDAV\DAV;

use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\INode;
use Sabre\DAV\Node;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Xml\Element\Response;
use Sabre\DAV\Xml\Response\MultiStatus;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Xml\Writer;
use SearchDAV\Backend\ISearchBackend;
use SearchDAV\Backend\SearchPropertyDefinition;
use SearchDAV\Backend\SearchResult;
use SearchDAV\XML\BasicSearch;
use SearchDAV\XML\BasicSearchSchema;
use SearchDAV\XML\PropDesc;
use SearchDAV\XML\QueryDiscoverResponse;
use SearchDAV\XML\Scope;
use SearchDAV\XML\SupportedQueryGrammar;

class SearchPlugin extends ServerPlugin {
	/** @var Server */
	private $server;

	/** @var ISearchBackend */
	private $searchBackend;

	/** @var QueryParser */
	private $queryParser;

	public function __construct(ISearchBackend $searchBackend) {
		$this->searchBackend = $searchBackend;
	}

	public function initialize(Server $server) {
		$this->server = $server;
		$this->queryParser = new QueryParser($this->server->xml);
		$server->on('method:SEARCH', [$this, 'searchHandler']);
		$server->on('afterMethod:OPTIONS', [$this, 'optionHandler']);
		$server->on('propFind', [$this, 'propFindHandler']);
	}

	public function propFindHandler(PropFind $propFind, INode $node) {
		if ($propFind->getPath() === $this->searchBackend->getArbiterPath()) {
			$propFind->handle('{DAV:}supported-query-grammar-set', new SupportedQueryGrammar());
		}
	}

	private function getPathFromUri($uri) {
		if (strpos($uri, '://') === false) {
			return $uri;
		}
		try {
			return ($uri === '' && $this->server->getBaseUri() === '/') ? '' : $this->server->calculateUri($uri);
		} catch (Forbidden $e) {
			return null;
		}
	}

	/**
	 * SEARCH is allowed for users files
	 *
	 * @param string $uri
	 * @return array
	 */
	public function getHTTPMethods($uri) {
		$path = $this->getPathFromUri($uri);
		if ($this->searchBackend->getArbiterPath() === $path) {
			return ['SEARCH'];
		} else {
			return [];
		}
	}

	public function optionHandler(RequestInterface $request, ResponseInterface $response) {
		if ($request->getPath() === '') {
			$response->addHeader('DASL', '<DAV:basicsearch>');
		}
	}

	public function searchHandler(RequestInterface $request, ResponseInterface $response) {
		$contentType = $request->getHeader('Content-Type');

		// Currently we only support xml search queries
		if ((strpos($contentType, 'text/xml') === false) && (strpos($contentType, 'application/xml') === false)) {
			return;
		}

		$xml = $this->queryParser->parse(
			$request->getBody(),
			$request->getUrl(),
			$documentType
		);

		switch ($documentType) {
			case '{DAV:}searchrequest':
				if (!$xml['{DAV:}basicsearch']) {
					throw new BadRequest('Unexpected xml content for searchrequest, expected basicsearch');
				}
				/** @var BasicSearch $query */
				$query = $xml['{DAV:}basicsearch'];
				$response->setStatus(207);
				$response->setHeader('Content-Type', 'application/xml; charset="utf-8"');
				foreach ($query->from as $scope) {
					$scope->path = $this->getPathFromUri($scope->href);
				}
				$results = $this->searchBackend->search($query);
				$data = $this->server->generateMultiStatus(iterator_to_array($this->getPropertiesIteratorResults($results, $query->select)), false);
				$response->setBody($data);
				return false;
			case '{DAV:}query-schema-discovery':
				if (!$xml['{DAV:}basicsearch']) {
					throw new BadRequest('Unexpected xml content for query-schema-discovery, expected basicsearch');
				}
				/** @var BasicSearch $query */
				$query = $xml['{DAV:}basicsearch'];
				$scopes = $query->from;
				$results = array_map(function (Scope $scope) {
					$scope->path = $this->getPathFromUri($scope->href);
					if ($this->searchBackend->isValidScope($scope->href, $scope->depth, $scope->path)) {
						$searchProperties = $this->searchBackend->getPropertyDefinitionsForScope($scope->href, $scope->path);
						$searchSchema = $this->getBasicSearchForProperties($searchProperties);
						return new QueryDiscoverResponse($scope->href, $searchSchema, 200);
					} else {
						return new QueryDiscoverResponse($scope->href, null, 404); // TODO something other than 404? 403 maybe
					}
				}, $scopes);
				$multiStatus = new MultiStatus($results);
				$response->setStatus(207);
				$response->setHeader('Content-Type', 'application/xml; charset="utf-8"');
				$response->setBody($this->queryParser->write('{DAV:}multistatus', $multiStatus, $request->getUrl()));
				return false;
			default:
				throw new BadRequest('Unexpected document type: ' . $documentType . ' for this Content-Type');
		}
	}

	/**
	 * Returns a list of properties for a given path
	 *
	 * The path that should be supplied should have the baseUrl stripped out
	 * The list of properties should be supplied in Clark notation. If the list is empty
	 * 'allprops' is assumed.
	 *
	 * If a depth of 1 is requested child elements will also be returned.
	 *
	 * @param SearchResult[] $results
	 * @param array $propertyNames
	 * @param int $depth
	 * @return \Iterator
	 */
	function getPropertiesIteratorResults($results, $propertyNames = [], $depth = 0) {
		$propFindType = $propertyNames ? PropFind::NORMAL : PropFind::ALLPROPS;

		foreach ($results as $result) {
			$node = $result->node;
			$propFind = new PropFind($result->href, (array)$propertyNames, $depth, $propFindType);
			$r = $this->server->getPropertiesByNode($propFind, $node);
			if ($r) {
				$result = $propFind->getResultForMultiStatus();
				$result['href'] = $propFind->getPath();

				// WebDAV recommends adding a slash to the path, if the path is
				// a collection.
				// Furthermore, iCal also demands this to be the case for
				// principals. This is non-standard, but we support it.
				$resourceType = $this->server->getResourceTypeForNode($node);
				if (in_array('{DAV:}collection', $resourceType) || in_array('{DAV:}principal', $resourceType)) {
					$result['href'] .= '/';
				}
				yield $result;
			}
		}
	}

	private function hashDefinition(SearchPropertyDefinition $definition) {
		return $definition->dataType
			. (($definition->searchable) ? '1' : '0')
			. (($definition->sortable) ? '1' : '0')
			. (($definition->selectable) ? '1' : '0');
	}

	/**
	 * @param SearchPropertyDefinition[] $propertyDefinitions
	 * @return BasicSearchSchema
	 */
	private function getBasicSearchForProperties(array $propertyDefinitions) {
		/** @var PropDesc[] $groups */
		$groups = [];
		foreach ($propertyDefinitions as $propertyDefinition) {
			$key = $this->hashDefinition($propertyDefinition);
			if (!isset($groups[$key])) {
				$desc = new PropDesc();
				$desc->dataType = $propertyDefinition->dataType;
				$desc->sortable = $propertyDefinition->sortable;
				$desc->selectable = $propertyDefinition->selectable;
				$desc->searchable = $propertyDefinition->searchable;
				$groups[$key] = $desc;
			}
			$groups[$key]->properties[] = $propertyDefinition->name;
		}

		return new BasicSearchSchema(array_values($groups));
	}
}
