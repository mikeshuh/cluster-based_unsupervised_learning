<?php
// runs the expectation-maximization algorithm on given data for 'k' clusters with specified precision and iteration limits
function emAlgorithm($data, $k, $epsilon = 1e-4, $maxIterations = 100)
{
    // check for valid input to prevent errors in processing
    if ($k > count($data)) {
        return 'Invalid input parameters';
    }

    // initialize parameters including means, variances, and mixing coefficients
    list($means, $variances, $mixingCoefficients) = initializeParameters($data, $k);

    // set the initial log likelihood to the lowest possible float value to start comparisons
    $previousLogLikelihood = PHP_FLOAT_MIN;

    // iterate through the EM steps up to a maximum number of iterations
    for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
        // perform the expectation step to calculate responsibilities
        $responsibilities = eStep($data, $means, $variances, $mixingCoefficients, $k);

        // perform the maximization step to update parameters based on responsibilities
        list($means, $variances, $mixingCoefficients) = mStep($data, $responsibilities, $k);

        // calculate the log-likelihood of the current model
        $currentLogLikelihood = calculateLogLikelihood($data, $means, $variances, $mixingCoefficients);

        // check if the improvement in log likelihood is below a small threshold to determine convergence
        if (abs($currentLogLikelihood - $previousLogLikelihood) < $epsilon) {
            break; // stops iterations if the model has converged
        }

        // update the log likelihood for the next iteration
        $previousLogLikelihood = $currentLogLikelihood;
    }

    // return the final parameters of the model after convergence or max iterations
    return [
        'means' => $means,
        'variances' => $variances,
        'mixingCoefficients' => $mixingCoefficients
    ];
}

// initializes parameters for the gaussian mixture model, distributing means across the data range and setting initial variances and mixing coefficients
function initializeParameters($data, $k)
{
    // find the minimum and maximum of the data to determine the range
    $min = min($data);
    $max = max($data);
    $means = [];
    $variances = [];
    $mixingCoefficients = [];

    // spread means evenly across the range and set initial variances and mixing coefficients
    $spread = ($max - $min) / ($k + 1);
    for ($i = 1; $i <= $k; $i++) {
        $means[] = $min + $i * $spread;
        $variances[] = max(($max - $min) / $k, 1e-2); // ensure non-zero variance
        $mixingCoefficients[] = 1 / $k;
    }

    return [$means, $variances, $mixingCoefficients];
}

// calculates the responsibilities for each data point to belong to each cluster based on current model parameters
function eStep($data, $means, $variances, $mixingCoefficients, $k)
{
    $responsibilities = [];
    foreach ($data as $x) {
        $weights = [];
        $totalWeight = 0;
        for ($j = 0; $j < $k; $j++) {
            // compute the probability of x for each component multiplied by the component's mixing coefficient
            $weights[$j] = gaussian($x, $means[$j], $variances[$j]) * $mixingCoefficients[$j];
            $totalWeight += $weights[$j];
        }
        // normalize weights to get responsibilities
        foreach ($weights as $j => $weight) {
            $responsibilities[$x][$j] = $weight / $totalWeight;
        }
    }
    return $responsibilities;
}

// updates the parameters of the model based on the responsibilities calculated in the e-step
function mStep($data, $responsibilities, $k)
{
    $means = array_fill(0, $k, 0);
    $variances = array_fill(0, $k, 0);
    $mixingCoefficients = array_fill(0, $k, 0);
    $totalResponsibility = array_fill(0, $k, 0);

    // aggregate weighted sums for each cluster
    foreach ($data as $x) {
        for ($j = 0; $j < $k; $j++) {
            $means[$j] += $x * $responsibilities[$x][$j];
            $totalResponsibility[$j] += $responsibilities[$x][$j];
        }
    }

    // update each parameter based on the total weights
    for ($j = 0; $j < $k; $j++) {
        if ($totalResponsibility[$j] > 0) {
            $means[$j] /= $totalResponsibility[$j];
            $varianceSum = 0;
            foreach ($data as $x) {
                $varianceSum += $responsibilities[$x][$j] * ($x - $means[$j]) ** 2;
            }
            $variances[$j] = max($varianceSum / $totalResponsibility[$j], 1e-2);
            $mixingCoefficients[$j] = $totalResponsibility[$j] / count($data);
        }
    }

    return [$means, $variances, $mixingCoefficients];
}

// calculates the gaussian probability density function for a given x, mean, and variance
function gaussian($x, $mean, $variance)
{
    // avoid division by zero by checking for zero variance
    if ($variance == 0) {
        return 0;
    }
    // calculate the normal distribution probability density
    $coeff = 1 / sqrt(2 * pi() * $variance);
    $exp = exp(-pow($x - $mean, 2) / (2 * $variance));
    return $coeff * $exp;
}

// calculates the log likelihood of the data under the current model parameters
function calculateLogLikelihood($data, $means, $variances, $mixingCoefficients)
{
    $logLikelihood = 0;
    // sum log probabilities across all data points
    foreach ($data as $x) {
        $componentLikelihood = 0;
        for ($j = 0; $j < count($means); $j++) {
            // sum probabilities from each gaussian component
            $componentLikelihood += $mixingCoefficients[$j] * gaussian($x, $means[$j], $variances[$j]);
        }
        // take logarithm of total probability for the data point and sum
        $logLikelihood += log($componentLikelihood);
    }
    return $logLikelihood;
}

// calculate probabilites each testing score belongs to each cluster
function calculateClusterProbabilities($newData, $means, $variances, $mixingCoefficients)
{
    $results = [];

    foreach ($newData as $dataPoint) {
        $probabilities = [];
        $totalProbability = 0;

        // calculate the weighted probabilities for each cluster
        foreach ($means as $index => $mean) {
            $gaussProb = gaussian($dataPoint, $mean, $variances[$index]);
            $weightedProb = $gaussProb * $mixingCoefficients[$index];
            $probabilities[$index] = $weightedProb;
            $totalProbability += $weightedProb;
        }

        // normalize probabilities so they sum to 1
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

// get means, variances, mixingCoeffs of trained EM model in db
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
