function validateName(field) {
  return field == '' ? 'No Name was entered.\n' : '';
}

function validateStudentId(field) {
  if (field == '') return 'No Student ID was entered.\n';
  else if (isNaN(field)) return 'Student ID must be a number.\n';
  else if (field < 0) return 'Student ID must be positive.\n';
  return '';
}

function validateUsername(field)
//at least 5 characters long & only a-z,A-Z,0-9,_,-
    {
        if (field == "") return "No Username was entered.\n" //checking for empty
        else if (field.length < 5) //checking for lenght of at least 5 characters 
            return "Usernames must be at least 5 characters.\n"
        //^ = represents a negated character class, which matches any character that is not specified within the square brackets
        else if (/[^a-zA-Z0-9_-]/.test(field)) // symbol ^ means any other one than a-zA-Z0-9_-
            return "Only a-z, A-Z, 0-9, - and _ allowed in Usernames.\n"
        return ""
    }

function validateEmail(field) {
  if (field == '') return 'No Email was entered.\n';
  else if (
    !(field.indexOf('.') > 0 && field.indexOf('@') > 0) ||
    /[^a-zA-Z0-9.@_-]/.test(field)
  )
    return 'The Email address is invalid.\n';
  return '';
}

function validatePassword(field) {
  if (field == '') return 'No Password was entered.\n';
  else if (field.length < 6)
    return 'Passwords must be at least 6 characters.\n';
  else if (!/[a-z]/.test(field) || !/[A-Z]/.test(field) || !/[0-9]/.test(field))
    return 'Passwords require one each of a-z, A-Z and 0-9.\n';
  return '';
}
