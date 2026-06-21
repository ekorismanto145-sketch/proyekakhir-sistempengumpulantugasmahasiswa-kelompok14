<?php
function storageRoot() {
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $configured = getenv('UPLOAD_STORAGE_PATH');
    $root = $configured !== false && trim($configured) !== ''
        ? rtrim(trim($configured), '/\\')
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    return $root;
}

function storageDirectory($subdirectory = '') {
    $safe_subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');
    if ($safe_subdirectory !== '' && preg_match('#(^|/)\.\.(/|$)#', $safe_subdirectory)) {
        throw new InvalidArgumentException('Invalid storage directory.');
    }

    $directory = storageRoot();
    if ($safe_subdirectory !== '') {
        $directory .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safe_subdirectory);
    }
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Storage directory could not be created.');
    }
    return $directory;
}

function storageRelativePath($subdirectory, $filename) {
    $safe_subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');
    return 'uploads/' . ($safe_subdirectory !== '' ? $safe_subdirectory . '/' : '') . basename($filename);
}

function storageCandidatePaths($stored_path) {
    if (!$stored_path) {
        return [];
    }

    $normalized = str_replace('\\', '/', trim($stored_path));
    if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
        return [];
    }

    $candidates = [];
    if (preg_match('#^[A-Za-z]:/#', $normalized) || str_starts_with($normalized, '/')) {
        $candidates[] = str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        return $candidates;
    }

    $relative = str_starts_with($normalized, 'uploads/')
        ? substr($normalized, strlen('uploads/'))
        : $normalized;
    $relative = ltrim($relative, '/');

    $candidates[] = storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $candidates[] = dirname(storageRoot()) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    $basename = basename($relative);
    if ($basename !== '') {
        $candidates[] = storageRoot() . DIRECTORY_SEPARATOR . $basename;
        $candidates[] = dirname(storageRoot()) . DIRECTORY_SEPARATOR . $basename;
    }

    return array_values(array_unique($candidates));
}

function storageAbsolutePath($stored_path) {
    foreach (storageCandidatePaths($stored_path) as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $candidates = storageCandidatePaths($stored_path);
    return $candidates[0] ?? null;
}
