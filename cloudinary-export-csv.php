<?php

/**
 * CloudinaryResourcesExporter
 *
 * Modern, flexible exporter for Cloudinary resources using the Admin API.
 * Supports filtering by prefix (folder), specific resource types, delivery type,
 * and optional inclusion of tags/context. Can also export only folder list.
 */
class CloudinaryResourcesExporter
{
	private array $credentials;

	private const DEFAULT_MAX_RESULTS = 500;
	private const DEFAULT_RESOURCE_TYPES = ['image', 'video', 'raw'];

	/**
	 * Constructor - accepts credentials array
	 *
	 * @param array $credentials Required keys: api_key, api_secret, cloud_name
	 */
	public function __construct(array $credentials)
	{
		$this->validateCredentials($credentials);
		$this->credentials = $credentials;
	}

	/**
	 * Export resources based on optional configuration and output as CSV
	 *
	 * @param array $config Optional configuration overrides
	 */
	public function exportAsCsv(array $config = []): void
	{
		$csvContent = $this->generateCsvContent($config);

		$filename = $config['filename'] ?? 'cloudinary-resources-list.csv';

		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename=\"{$filename}\"");
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

		echo $csvContent;
		exit;
	}

	/**
	 * Validate required credential keys
	 */
	private function validateCredentials(array $credentials): void
	{
		$required = ['api_key', 'api_secret', 'cloud_name'];
		foreach ($required as $key) {
			if (empty($credentials[$key])) {
				throw new InvalidArgumentException("Missing required credential key: {$key}");
			}
		}
	}

	/**
	 * Generate full CSV content based on config
	 */
	private function generateCsvContent(array $config): string
	{
		if (!empty($config['folders_only'])) {
			return $this->generateFoldersCsv($config);
		}

		return $this->generateResourcesCsv($config);
	}

	/**
	 * Generate CSV for resources (images, videos, raw files)
	 */
	private function generateResourcesCsv(array $config): string
	{
		$csvContent = '';
		$isFirstBatch = true;

		$resourceTypes = $config['resource_types'] ?? self::DEFAULT_RESOURCE_TYPES;

		foreach ($resourceTypes as $resourceType) {
			$nextCursor = null;

			do {
				$response = $this->fetchResources($config, $resourceType, $nextCursor);

				if (!empty($response['resources'])) {
					$csvContent .= $this->resourcesToCsv(
						$response['resources'],
						$resourceType,
						$isFirstBatch && $csvContent === ''
					);
					$isFirstBatch = false;
				}

				$nextCursor = $response['next_cursor'] ?? null;
			} while ($nextCursor);
		}

		return $csvContent;
	}

	/**
	 * Generate CSV containing all folders (recursive tree)
	 */
	private function generateFoldersCsv(array $config): string
	{
		$allFolders = $this->fetchAllFoldersRecursively($config);

		$csv = "folder\r\n";
		foreach ($allFolders as $folder) {
			$csv .= $folder . "\r\n";
		}

		return $csv;
	}

	/**
	 * Recursively fetch all folders using dedicated /folders endpoints
	 */
	private function fetchAllFoldersRecursively(array $config, string $parent = ''): array
	{
		$folders = [];

		$nextCursor = null;
		do {
			$response = $this->fetchFoldersPage($config, $parent, $nextCursor);

			foreach ($response['folders'] ?? [] as $folderData) {
				$folderName = $folderData['name'];
				$fullPath   = $parent ? $parent . '/' . $folderName : $folderName;

				$folders[] = $fullPath;

				// Recursively get subfolders
				$folders = array_merge($folders, $this->fetchAllFoldersRecursively($config, $fullPath));
			}

			$nextCursor = $response['next_cursor'] ?? null;
		} while ($nextCursor);

		// Add root indicator if no folders
		if (empty($folders) && $parent === '') {
			$folders[] = '(root)';
		}

		return $folders;
	}

	/**
	 * Fetch one page of (sub)folders using Cloudinary /folders endpoint
	 */
	private function fetchFoldersPage(array $config, string $parent, ?string $nextCursor): array
	{
		$baseUrl = 'https://' . $this->credentials['api_key'] . ':' . $this->credentials['api_secret']
		         . '@api.cloudinary.com/v1_1/' . $this->credentials['cloud_name'];

		$path = $parent ? '/folders/' . rawurlencode($parent) : '/folders';

		$params = [
			'max_results' => $config['max_results'] ?? 500,
		];

		if ($nextCursor) {
			$params['next_cursor'] = $nextCursor;
		}

		$queryString = http_build_query($params);
		$url = $baseUrl . $path . '?' . $queryString;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Cloudinary-Exporter/2.0');

		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new RuntimeException('cURL Error: ' . curl_error($ch));
		}

		curl_close($ch);

		$data = json_decode($response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('Invalid JSON response from Cloudinary API');
		}

		if (isset($data['error'])) {
			throw new RuntimeException('Cloudinary API Error: ' . $data['error']['message']);
		}

		return $data;
	}

	/**
	 * Fetch one page of resources from Cloudinary Admin API
	 */
	private function fetchResources(array $config, string $resourceType, ?string $nextCursor): array
	{
		$deliveryType = $config['type'] ?? 'upload';  // Default: upload

		$baseUrl = sprintf(
			'https://%s:%s@api.cloudinary.com/v1_1/%s/resources/%s/%s',
			$this->credentials['api_key'],
			$this->credentials['api_secret'],
			$this->credentials['cloud_name'],
			$resourceType,
			$deliveryType
		);

		$params = [
			'max_results' => $config['max_results'] ?? self::DEFAULT_MAX_RESULTS,
		];

		if (!empty($config['prefix'])) {
			$params['prefix'] = $config['prefix'];
		}

		if (!empty($config['tags']) && $config['tags'] === true) {
			$params['tags'] = 'true';
		}

		if (!empty($config['context']) && $config['context'] === true) {
			$params['context'] = 'true';
		}

		if ($nextCursor) {
			$params['next_cursor'] = $nextCursor;
		}

		$queryString = http_build_query($params);
		$url = $baseUrl . ($queryString ? '?' . $queryString : '');

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Cloudinary-Exporter/2.0');

		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new RuntimeException('cURL Error: ' . curl_error($ch));
		}

		curl_close($ch);

		$data = json_decode($response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('Invalid JSON response from Cloudinary API');
		}

		if (isset($data['error'])) {
			throw new RuntimeException('Cloudinary API Error: ' . $data['error']['message']);
		}

		return $data;
	}

	/**
	 * Convert resources array to tab-separated CSV lines
	 */
	private function resourcesToCsv(array $resources, string $resourceType, bool $includeHeader): string
	{
		$csvLines = '';

		foreach ($resources as $index => $resource) {
			$normalized = $this->normalizeResourceColumns($resource, $resourceType);

			if ($includeHeader && $index === 0) {
				$csvLines .= implode("\t", array_keys($normalized)) . "\r\n";
			}

			$values = array_map(function ($value) {
				if ($value === null) {
					return '';
				}
				if (is_array($value)) {
					return implode(', ', array_map('strval', $value));
				}
				if (is_bool($value)) {
					return $value ? 'true' : 'false';
				}
				if (is_object($value)) {
					return json_encode($value); // 
				}
				return str_replace("\t", ' ', (string)$value);
			}, array_values($normalized));

			$csvLines .= implode("\t", $values) . "\r\n";
		}

		return $csvLines;
	}

	/**
	 * Ensure consistent column order across resource types
	 */
	private function normalizeResourceColumns(array $resource, string $resourceType): array
	{
		$normalized = $resource;

		if ($resourceType === 'raw') {
			$normalized = $this->arrayInsert($normalized, 'format', 2, '');
			$normalized = $this->arrayInsert($normalized, 'width', 8, '');
			$normalized = $this->arrayInsert($normalized, 'height', 9, '');
		}

		if (in_array($resourceType, ['image', 'video']) && !isset($normalized['access_mode'])) {
			$keys = array_keys($normalized);
			$pos = array_search('bytes', $keys) + 1;
			$normalized = $this->arrayInsert($normalized, 'access_mode', $pos, '');
		}

		return $normalized;
	}

	/**
	 * Insert a key-value pair at specific position in associative array
	 */
	private function arrayInsert(array $array, string $newKey, int $position, $newValue): array
	{
		$keys = array_keys($array);
		$values = array_values($array);

		array_splice($keys, $position, 0, $newKey);
		array_splice($values, $position, 0, $newValue);

		return array_combine($keys, $values);
	}
}

// =============================================================================
// Usage Examples
// =============================================================================

$credentials = [
	'api_key'    => 'YOUR_API_KEY',		// <--- Replace with your Cloudinary API Key
	'api_secret' => 'YOUR_API_SECRET',	// <--- Replace with your Cloudinary API Secret
	'cloud_name' => 'YOUR_CLOUD_NAME',	// <--- Replace with your Cloudinary Cloud Name
];

try {
	$exporter = new CloudinaryResourcesExporter($credentials);

	// Example 1: All resources (default)
	$exporter->exportAsCsv();

	// Example 2: Only list all folders
	// $exporter->exportAsCsv([
	// 	'folders_only'	=> true,
	// 	'filename'		=> 'cloudinary-folders.csv',
	// ]);

	// Example 3: Only images in a specific folder
	// $exporter->exportAsCsv([
	// 	'prefix'			=> 'products/2025/',
	// 	'resource_types'	=> ['image'],
	// 	'tags'				=> true,
	// 	'context'			=> true,
	// ]);

	// Example X: Only images in a specific folder
	// $exporter->exportAsCsv([
	// 	'prefix'		=> 'wp/import/d',
	// 	'filename'		=> 'cloudinary-resources-list-folder.csv',
	// ]);

} catch (Exception $e) {
	http_response_code(500);
	echo 'Error: ' . htmlspecialchars($e->getMessage());
}
