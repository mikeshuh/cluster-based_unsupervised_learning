<?php
function emAlgorithm($data, $k, $epsilon = 0.001, $maxIterations = 100)
{
    // Step 1: Improved Initialization
    list($means, $variances, $mixingCoefficients) = initializeParameters($data, $k);

    $previousMeans = $means;
    for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
        // Step 2: E-step
        $responsibilities = eStep($data, $means, $variances, $mixingCoefficients, $k);

        // Step 3: M-step
        list($means, $variances, $mixingCoefficients) = mStep($data, $responsibilities, $k);

        // Check for convergence
        $converged = true;
        for ($j = 0; $j < $k; $j++) {
            if (abs($means[$j] - $previousMeans[$j]) > $epsilon) {
                $converged = false;
                break;
            }
        }

        if ($converged) {
            break;
        }

        $previousMeans = $means;
    }

    return [
        'means' => $means,
        'variances' => $variances,
        'mixingCoefficients' => $mixingCoefficients
    ];
}

function initializeParameters($data, $k)
{
    $min = min($data);
    $max = max($data);
    $means = [];
    $variances = [];
    $mixingCoefficients = [];

    $spread = ($max - $min) / ($k + 1);
    for ($i = 1; $i <= $k; $i++) {
        $means[] = $min + $i * $spread;
        $variances[] = ($max - $min) / $k;
        $mixingCoefficients[] = 1 / $k;
    }

    return [$means, $variances, $mixingCoefficients];
}


function eStep($data, $means, $variances, $mixingCoefficients, $k)
{
    $responsibilities = [];
    foreach ($data as $x) {
        $weights = [];
        $totalWeight = 0;
        for ($j = 0; $j < $k; $j++) {
            $weights[$j] = gaussian($x, $means[$j], $variances[$j]) * $mixingCoefficients[$j];
            $totalWeight += $weights[$j];
        }
        foreach ($weights as $j => $weight) {
            $responsibilities[$x][$j] = $weight / $totalWeight;
        }
    }
    return $responsibilities;
}

function mStep($data, $responsibilities, $k)
{
    $means = array_fill(0, $k, 0);
    $variances = array_fill(0, $k, 0);
    $mixingCoefficients = array_fill(0, $k, 0);
    $totalResponsibility = array_fill(0, $k, 0);

    foreach ($data as $x) {
        for ($j = 0; $j < $k; $j++) {
            $means[$j] += $x * $responsibilities[$x][$j];
            $totalResponsibility[$j] += $responsibilities[$x][$j];
        }
    }

    for ($j = 0; $j < $k; $j++) {
        if ($totalResponsibility[$j] > 0) {
            $means[$j] /= $totalResponsibility[$j];
            $varianceSum = 0;
            foreach ($data as $x) {
                $varianceSum += $responsibilities[$x][$j] * ($x - $means[$j]) ** 2;
            }
            $variances[$j] = $varianceSum / $totalResponsibility[$j];
            $mixingCoefficients[$j] = $totalResponsibility[$j] / count($data);
        }
    }

    return [$means, $variances, $mixingCoefficients];
}

function gaussian($x, $mean, $variance)
{
    if ($variance == 0) {
        return 0; // Avoid division by zero
    }
    $coeff = 1 / sqrt(2 * pi() * $variance);
    $exp = exp(-pow($x - $mean, 2) / (2 * $variance));
    return $coeff * $exp;
}

function calculateClusterProbabilities($newData, $means, $variances, $mixingCoefficients)
{
    $results = [];

    foreach ($newData as $dataPoint) {
        $probabilities = [];
        $totalProbability = 0;

        // Calculate the weighted probabilities for each cluster
        foreach ($means as $index => $mean) {
            $gaussProb = gaussian($dataPoint, $mean, $variances[$index]);
            $weightedProb = $gaussProb * $mixingCoefficients[$index];
            $probabilities[$index] = $weightedProb;
            $totalProbability += $weightedProb;
        }

        // Normalize probabilities so they sum to 1
        foreach ($probabilities as $index => $prob) {
            $probabilities[$index] = $prob / $totalProbability;
        }

        $results[] = [
            'dataPoint' => $dataPoint,
            'probabilities' => $probabilities
        ];
    }

    return $results;
}

function getEMaxClusterProperties($username, $modelName)
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM e_max WHERE username=? AND model_name=?');
    $stmt->bind_param('ss', $username, $modelName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $means = stringToNumbersArray($row['means']);
    $variances = stringToNumbersArray($row['variances']);
    $mixingCoeffs = stringToNumbersArray($row['mixing_coeffs']);
    return [$means, $variances, $mixingCoeffs];
}

// add EM model to db
function insertDBEM($modelName, $username, $modelType, $means, $variances, $mixingCoeffs)
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO user_models VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $modelName, $username, $modelType);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO e_max VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $modelName, $username, $means, $variances, $mixingCoeffs);
    $stmt->execute();
    $stmt->close();
}
