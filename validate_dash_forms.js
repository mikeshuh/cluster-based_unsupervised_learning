function validateModelName(field) {
  return field == '' ? 'Please enter model name.\n' : '';
}

function validateAlgo(field) {
  return field == 'none' ? 'Please select clustering algorithm.\n' : '';
}

function validateNumClusters(field) {
  if (field == '') return 'Please enter number of clusters.\n';
  else if (field < 1) return 'Number of clusters must be greater than 0.\n';
  return '';
}

function validateInputType(field) {
  return field == 'none' ? 'Please select input type for training.\n' : '';
}

function validateFileUpload(field) {
  return field == 0 ? 'Please select a file to upload.\n' : '';
}

function validateTextInput(field) {
  if (field == '') return 'Please enter text for training.\n';
  else if (!/^\d+(,\d+)*$/.test(field)) return 'Text input must only consist of numbers separated by commas.\n';
  return '';
}

function validateModelSelect(field) {
  return field == 'none' ? 'Please select model to text.\n' : '';
}