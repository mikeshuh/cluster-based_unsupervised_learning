// no empty input
function validateModelName(field) {
  return field == '' ? 'Please enter model name.\n' : '';
}

// must make selection
function validateAlgo(field) {
  return field == 'none' ? 'Please select clustering algorithm.\n' : '';
}

// no empty input. input must be greater than 0
function validateNumClusters(field) {
  if (field == '') return 'Please enter number of clusters.\n';
  else if (field < 1 || field > 10) return 'Number of clusters must be 1-10.\n';
  return '';
}

// must make selection
function validateInputType(field) {
  return field == 'none' ? 'Please select input type for training.\n' : '';
}

// must upload a file
function validateFileUpload(field) {
  return field == 0 ? 'Please select a file to upload.\n' : '';
}

// no empty input. input must be numbers separated only by commas
function validateTextInput(field) {
  if (field == '') return 'Please enter text for training.\n';
  else if (!/^\d+(,\d+)*$/.test(field)) return 'Text input must only consist of numbers separated by commas.\n';
  return '';
}

// must make selection
function validateModelSelect(field) {
  return field == 'none' ? 'Please select model to text.\n' : '';
}