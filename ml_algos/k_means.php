<?php
function kMeans($data, $k)
{
    // Initialize centroids randomly
    $centroids = initializeCentroids($data, $k);

    // Initialize a variable to track changes in centroid assignments
    $assignments = [];
    $changed = true;

    // Iterate until centroid positions stabilize
    while ($changed) {
        $newAssignments = assignPoints($data, $centroids);
        $centroids = recalculateCentroids($data, $newAssignments, $k);
        $changed = !($newAssignments === $assignments);
        $assignments = $newAssignments;
    }

    return $centroids;
}

function initializeCentroids($data, $k)
{
    $min = min($data);
    $max = max($data);
    $centroids = [];

    for ($i = 0; $i < $k; $i++) {
        $centroids[] = mt_rand($min, $max);
    }

    return $centroids;
}

function assignPoints($data, $centroids)
{
    $assignments = [];

    foreach ($data as $point) {
        $closest = null;
        $closestDistance = PHP_INT_MAX;

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

    foreach ($data as $index => $point) {
        $sums[$assignments[$index]] += $point;
        $counts[$assignments[$index]]++;
    }

    $centroids = [];
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
