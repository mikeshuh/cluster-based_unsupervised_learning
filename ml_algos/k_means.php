<?php
function kMeans($data, $k)
{
    // Initialize centroids randomly from the data range
    $centroids = initializeCentroids($data, $k);

    // Initialize an empty array to store point assignments to centroids
    $assignments = [];
    // Variable to check if there's any change in the assignments
    $changed = true;

    // Loop until no changes in assignments
    while ($changed) {
        // Assign each point to the nearest centroid
        $newAssignments = assignPoints($data, $centroids);
        // Recalculate centroids based on the assignments
        $centroids = recalculateCentroids($data, $newAssignments, $k);
        // Check if assignments have changed, if not, stop the algorithm
        $changed = !($newAssignments === $assignments);
        // Update assignments
        $assignments = $newAssignments;
    }

    // Return centroids as a comma-separated string
    return $centroids;
}

function initializeCentroids($data, $k)
{
    // Get minimum and maximum values from the data
    $min = min($data);
    $max = max($data);
    $centroids = [];

    // Randomly generate 'k' centroids within the range of the data
    for ($i = 0; $i < $k; $i++) {
        $centroids[] = mt_rand($min, $max);
    }

    return $centroids;
}

function assignPoints($data, $centroids)
{
    $assignments = [];

    // Assign each point in the data to the closest centroid
    foreach ($data as $point) {
        $closest = null;
        $closestDistance = PHP_INT_MAX;

        // Determine the closest centroid
        foreach ($centroids as $index => $centroid) {
            $distance = abs($centroid - $point);
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closest = $index;
            }
        }

        $assignments[] = $closest;
    }

    return $assignments;
}

function recalculateCentroids($data, $assignments, $k)
{
    $sums = array_fill(0, $k, 0);
    $counts = array_fill(0, $k, 0);

    // Sum up and count the points for each centroid
    foreach ($data as $index => $point) {
        $sums[$assignments[$index]] += $point;
        $counts[$assignments[$index]]++;
    }

    $centroids = [];
    // Calculate new centroids by averaging the sums of assigned points
    foreach ($sums as $index => $sum) {
        if ($counts[$index] > 0) {
            $centroids[$index] = $sum / $counts[$index];
        } else {
            // Reinitialize centroid randomly if no points were assigned
            $centroids[$index] = mt_rand(min($data), max($data));
        }
    }

    return $centroids;
}

// turn data assignments into clusters containing all the assigned data points
function groupPointsByCluster($data, $assignments, $numClusters)
{
    $clusters = [];
    for ($i = 0; $i < $numClusters; $i++) {
        $clusters[$i] = [];
    }
    foreach ($assignments as $index => $clusterIndex) {
        $clusters[$clusterIndex][] = $data[$index];
    }
    return $clusters;
}

// return centriod string of user model
function getKClusterCentroids($username, $modelName)
{
    global $conn;
    $stmt = $conn->prepare('SELECT centroids FROM k_means WHERE username=? AND model_name=?');
    $stmt->bind_param('ss', $username, $modelName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['centroids'];
}

// add k means model to db
function insertDBKCluster($modelName, $username, $modelType, $centroids)
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO user_models VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $modelName, $username, $modelType);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO k_means VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $modelName, $username, $centroids);
    $stmt->execute();
    $stmt->close();
}
