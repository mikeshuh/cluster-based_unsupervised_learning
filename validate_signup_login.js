function validateUsername(field)
//only a-z,A-Z,0-9,_,-
    {
        if (field == "") return "No Username was entered.\n"
        else if (/[^a-zA-Z0-9_-]/.test(field)) 
            return "Only a-z, A-Z, 0-9, - and _ allowed in Usernames.\n"
        return ""
    }

function validateEmail(field) {
  if (field == '') return 'No Email was entered.\n';
  else if (
    !(field.indexOf('.') > 0 && field.indexOf('@') > 0) || /[^a-zA-Z0-9.@_-]/.test(field)
  )
    return 'The Email address is invalid.\n';
  return '';
}

function validatePassword(field) {
  if (field == '') return 'No Password was entered.\n';
  else if (field.length < 8)
    return 'Passwords must be at least 8 characters.\n';
  else if (!/[a-z]/.test(field) || !/[A-Z]/.test(field) || !/[0-9]/.test(field))
    return 'Passwords require one each of a-z, A-Z and 0-9.\n';
  return '';
}
