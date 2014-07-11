
// @(#) $Id: s.validation.js 1.13 03/10/20 21:24:54-00:00 jpradomaia $
function isWhitespace(s)
{
    var whitespace = " \t\n\r";

    if (s == null || s.length == 0 || s == '<br />' || s == '&nbsp;') {
        // empty field!
        return true;
    } else {
        // check for whitespace now!
        for (var z = 0; z < s.length; z++) {
            // Check that current character isn't whitespace.
            var c = s.charAt(z);
            if (whitespace.indexOf(c) == -1) return false;
        }
        return true;
    }
}

function isFloat(s)
{
    if (isWhitespace(s)) {
        return false;
    }

    var seenDecimalPoint = false;
    if (s == '.') {
        return false;
    }
    // Search through string's characters one by one
    // until we find a non-numeric character.
    // When we do, return false; if we don't, return true.
    for (var i = 0; i < s.length; i++) {
        // Check that current character is number.
        var c = s.charAt(i);
        if ((c == '.') && !seenDecimalPoint) {
            seenDecimalPoint = true;
        } else if (!isDigit(c)) {
            return false;
        }
    }

    // All characters are numbers.
    return true;
}

// @@@ CK - Added By CK 3/11/2004
function trim(inputString) {
   // Removes leading and trailing spaces from the passed string. Also removes
   // consecutive spaces and replaces it with one space. If something besides
   // a string is passed in (null, custom object, etc.) then return the input.
   if (typeof inputString != "string") {return inputString;}
   var retValue = inputString;
   var ch = retValue.substring(0, 1);
   while (ch == " ") { // Check for spaces at the beginning of the string
      retValue = retValue.substring(1, retValue.length);
      ch = retValue.substring(0, 1);
   }
   ch = retValue.substring(retValue.length-1, retValue.length);
   while (ch == " ") { // Check for spaces at the end of the string
      retValue = retValue.substring(0, retValue.length-1);
      ch = retValue.substring(retValue.length-1, retValue.length);
   }
   while (retValue.indexOf("  ") != -1) { // Note that there are two spaces in the string - look for multiple spaces within the string
      retValue = retValue.substring(0, retValue.indexOf("  ")) + retValue.substring(retValue.indexOf("  ")+1, retValue.length); // Again, there are two spaces in each of the strings
   }
   return retValue; // Return the trimmed string back to the user
} // Ends the "trim" function




//function to check valid email address
// @@@ CK Added 3/11/2004
function isValidEmail(strEmail){
	var validRegExp = /^[a-zA-Z][\w\.-]*[a-zA-Z0-9]@[a-zA-Z0-9][\w\.-]*[a-zA-Z0-9]\.[a-zA-Z][a-zA-Z\.]*[a-zA-Z]$/i;
	strEmail = trim(strEmail);
   // search email text for regular exp matches
    if (strEmail.search(validRegExp) == -1)
   {
      return false;
    }
    return true;
}

function isEmail(s)
{
    // email text field.
    var sLength = s.length;
    var denied_chars = new Array(" ", "\n", "\t", "\r", "%", "$", "#", "!", "~", "`", "^", "&", "*", "(", ")", "=", "+", "{", "}", "[", "]", ",", ";", ":", "'", "\"", "?", "<", ">", "/", "\\", "|");

    // look for @
    if (s.indexOf("@") == -1) return false;

    // look for more than one @ sign
    if (s.indexOf("@") != s.lastIndexOf("@")) return false;

    // look for any special character
    for (var z = 0; z < denied_chars.length; z++) {
        if (s.indexOf(denied_chars[z]) != -1) return false;
    }

    // look for .
    if (s.indexOf(".") == -1) return false;

    // no two dots alongside each other
    if (s.indexOf("..") != -1) return false;

    // you can't have and @ and a dot
    if (s.indexOf("@.") != -1) return false;

    // the last character cannot be a .
    if ((s.charAt(sLength-1) == ".") || (s.charAt(sLength-1) == "_")) return false;

    return true;
}

function isURL(s) {
     //var v = new RegExp();
     //v.compile("^[A-Za-z]+://[A-Za-z0-9-_]+\\.[A-Za-z0-9-_%&\?\/.=]+$");

	var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
	return regexp.test(s);
}

function isMyPubURL(u) {
	var regexp = /^[a-z_]+$/;
	return regexp.test(u) && u.length < 101;
}

function isORCID(u) {
	var valid = (u.length == 19);
	valid = valid && (u.charAt(4) == '-') && (u.charAt(9) == '-') && (u.charAt(14) == '-');
    var total = 0;
    for (var i = 0; i < 18; i++) {
        if (u.charAt(i) != '-') {
            digit = Number(u.charAt(i));
            total = (total + digit) * 2;
        }
    }
    remainder = total % 11;
    result = (12 - remainder) % 11;
    valid = valid && ((result == Number(u.charAt(18)) || (result == 10 && u.charAt(18) == 'X' )));
    return valid;
}

function isPeopleAustraliaID(u) {
  return /^(http:\/\/nla\.gov\.au\/)?nla\.party\-\d+$/.test(u);
}

function isScopusID(u) {
  return isInteger(u);
}

function isGoogleScholarID(u) {
  return /^[A-Za-z0-9_]+$/.test(u);
}

function hasDeniedChars(s)
{
    var denied_chars = new Array(" ", "\n", "\t", "\r", "%", "$", "#", "!", "~", "`", "^", "&", "*", "(", ")", "=", "+", "{", "}", "[", "]", ",", ";", ":", "'", "\"", "?", "<", ">", "/", "\\", "|");

    for (var z = 0; z < denied_chars.length; z++) {
        if (s.indexOf(denied_chars[z]) != -1) return true;
        // checking for any non-ascii character
        if (s.charCodeAt(z) > 128) return true;
    }

    return false;
}

function hasOneSelected(f, field_name)
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            var multi = f.elements[i];
            for (var y = 0; y < multi.options.length; y++) {
                if (multi.options[y].selected) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Check if a select field has an option with value selected.
 * Return false when there is no option selected or selected value is empty, otherwise return true.
 * @param f
 * @param field_name
 */
function hasOneSelectedValue( f, field_name )
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            var multi = f.elements[i];
            for (var y = 0; y < multi.options.length; y++) {
                if (multi.options[y].selected && multi.options[y].value!="") {
                    return true;
                }
            }
        }
    }
    return false;
}

function hasSelected(field, value)
{
    return field.options[field.selectedIndex].value == value;
}

function hasOneChecked(f, field_name)
{
    var found = 0;
    for (var i = 0; i < f.elements.length; i++) {
        if ((f.elements[i].name == field_name) && (f.elements[i].checked)) {
            found = 1;
        }
    }
    if (found == 0) {
        return false;
    } else {
        return true;
    }
}

function hasOnlyOneChecked(f, field_name)
{
    var found = 0;
    var multiple = 0;
    for (var i = 0; i < f.elements.length; i++) {
        if ((f.elements[i].name == field_name) && (f.elements[i].checked)) {
			if (found == 1) {
				multiple = 1;
			}
            found = 1;
        }
    }
    if ((found == 0) || (multiple == 1)) {
        return false;
    } else {
        return true;
    }
}

//This functions goes back through previous entries and looks to ensure this entry is unique.
//It needs xsdmf_multiple and xsdmf_multiple_limit to be set.
function isUnique(s)
{
    //assume the name is of the form ie xsd_display_fields_6351_numberofitem
    var regexp = /.*_/;
    field_name = s.match(regexp);
    if (document.getElementById(s) != null) {
        field_value = document.getElementById(s).value;
    }
    var regexp = /[0-9]*$/;
    field_number = s.match(regexp);
    for (var i=0;i<field_number;i++)
    {
        prev_field = field_name+i;
        if ((document.getElementById(prev_field) != null) && (document.getElementById(prev_field).value == field_value) ) {
            return false;
        }
    }
    return true;
}

function isNumeric(sText)
{
   var ValidChars = "0123456789.";
   var IsNumber=true;
   var Char;


   for (i = 0; i < sText.length && IsNumber == true; i++)
      {
      Char = sText.charAt(i);
      if (ValidChars.indexOf(Char) == -1)
         {
         IsNumber = false;
         }
      }
   return IsNumber;

}

function isInteger(sText)
{
    var intRegex = /^\d+$/;
    if(intRegex.test(sText)) {
        return true;
    }
    return false;

}

function isNumberOnly(s)
{
    var check = parseFloat(s).toString();
    if ((s.length == check.length) && (check != "NaN")) {
        return true;
    } else {
        return false;
    }
}

function isDigit(c)
{
    return ((c >= "0") && (c <= "9"));
}

function isFloat(s)
{
    if (isWhitespace(s)) {
        return false;
    }

    var seenDecimalPoint = false;
    if (s == '.') {
        return false;
    }
    // Search through string's characters one by one
    // until we find a non-numeric character.
    // When we do, return false; if we don't, return true.
    for (var i = 0; i < s.length; i++) {
        // Check that current character is number.
        var c = s.charAt(i);
        if ((c == '.') && !seenDecimalPoint) {
            seenDecimalPoint = true;
        } else if (!isDigit(c)) {
            return false;
        }
    }

    // All characters are numbers.
    return true;
}

function startsWith(s, substr)
{
    if (s.indexOf(substr) == 0) {
        return true;
    } else {
        return false;
    }
}

function errorDetailsIcon(f, field_name, show)
{
    var icon = getPageElement('error_icon_' + field_name);

	if (icon == null) {
        return false;
    }
    if (show) {
        icon.style.visibility = 'visible';
        icon.width = 14;
        icon.height = 14;
    } else {
        icon.style.visibility = 'hidden';
        icon.width = 1;
        icon.height = 1;
    }
}


function errorDetailsField(f, field_name, show)
{
    var field = getFormElement(f, field_name);
	if (field == false) {
        return false;
    }
    if (show) {
        field.style.backgroundColor = '#FF9999';
    } else {
        field.style.backgroundColor = '#FFFFFF';
    }
}


function errorDetails(f, field_name, show)
{
    errorDetailsIcon(f, field_name, show);
    errorDetailsField(f, field_name, show)
}

function getFieldTitle(titles_array, field_name)
{
    for (var i = 0; i < titles_array.length; i++) {
        if (titles_array[i].text == field_name) {
            return titles_array[i].value;
        }
    }
}

function checkRequiredFieldsExt(f, required_fields, required_fields_titles)
{
    for (var i = 0; i < required_fields.length; i++) {

		var field = getFormElement(f, required_fields[i].text);
		if (required_fields[i].value == 'combo') {
            if ((getSelectedOption(f, field.name) == '-1') || (getSelectedOption(f, field.name) == '') ) {
                errors[errors.length] = new Option(getFieldTitle(required_fields_titles,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'multiple') {
            if (!hasOneSelectedValue(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(required_fields_titles,required_fields[i].text), required_fields[i].text);
			}
        } else if (required_fields[i].value == 'checkbox') {
            if (!hasOnlyOneChecked(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(required_fields_titles,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'date') {
			if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(required_fields_titles,required_fields[i].text), required_fields[i].text);
            }
		} else if (required_fields[i].value == 'whitespace') {
			if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(required_fields_titles,required_fields[i].text), required_fields[i].text);
            }
        }
    }
}

function checkRequiredCustomFields(f, required_fields)
{
    for (var i = 0; i < required_fields.length; i++) {
        var field = getFormElement(f, required_fields[i].text);
        if (required_fields[i].value == 'combo') {
            if ((getSelectedOption(f, field.name) == '-1') || (getSelectedOption(f, field.name) == '')) {
                errors[errors.length] = new Option(getFieldTitle(custom_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'multiple') {
            if (!hasOneSelectedValue(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(custom_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'checkbox') {
            if (!hasOnlyOneChecked(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(custom_fields,required_fields[i].text), required_fields[i].text);
            }
		} else if (required_fields[i].value == 'whitespace') {
            if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(custom_fields,required_fields[i].text), required_fields[i].text);
            }
        }
    }
}
function xsdmfValidate(field, value, vtype, title, name) {
	if (vtype == 'numeric') {
		if (!isWhitespace(value) && !isNumeric(value)) {
            errors[errors.length] = new Option(title+' (needs to be in numeric format)', name);
		}
	} else if (vtype == 'date') {
		if (!isWhitespace(value) && !isDate(value)) {
            errors[errors.length] = new Option(title+' (needs to be in date format)', name);
		}
	} else if (vtype == 'email') {
		if (!isWhitespace(value) && !isEmail(value)) {
            errors[errors.length] = new Option(title+' (needs to be in email format)', name);
		}
	} else if (vtype == 'url') {
		if (!isWhitespace(value) && !isURL(value)) {
            errors[errors.length] = new Option(title+' (needs to be in URL format eg http://www.example.com, are you missing the http:// ?)', name);
		}
	} else if (vtype == 'unique') {
        if (value != '' && value != 0 && !isUnique(name)) {
            errors[errors.length] = new Option(title+'s (needs to be unique)', name);
        }
	} else if (vtype == 'integer') {
        if (value != '' && !isInteger(value)) {
            errors[errors.length] = new Option(title+'s (needs to be integer)', name);
        }
    }
}

function xsdmfValidateLength(field, value, maxLength, title, name) {
	var currentLength = field.value.length;
	if (maxLength != null && maxLength > 0 && currentLength > maxLength)
        errors[errors.length] = new Option(title+' (cannot exceed '+maxLength+' characters [current length='+currentLength+'])', name);
}

function checkRequiredFields(f, required_fields)
{
    for (var i = 0; i < required_fields.length; i++) {
        var field = getFormElement(f, required_fields[i].text);
        if (required_fields[i].value == 'combo') {
            if ((getSelectedOption(f, field.name) == '-1') || (getSelectedOption(f, field.name) == '')) {
                errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'multiple') {
            if (!hasOneSelectedValue(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'checkbox') {
            if (!hasOnlyOneChecked(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'date') {
            if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'whitespace') {
            if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
            }



        } else if (required_fields[i].value == 'fileupload_perms') {
        // Initial file validation: check if there is file(s) on the queue
            if (typeof(swfuploader) != 'undefined') {
                var stats = swfuploader.getStats();
                if (stats.files_queued > 0) {
                   var x = 0;
                   for (var x = 0; x < stats.files_queued; x++) {
                     if (document.getElementById('filePermissionsNew[' + x + ']').selectedIndex == 0) {
                        document.getElementById('uploaderUploadButton').style.backgroundColor = '#FF9999';
                        errors[errors.length] = new Option('File Classification', 'File Classification');
                     }
                   }
                }
             }
        } else if (required_fields[i].value == 'fileupload') {
           if (typeof(swfuploader) != 'undefined') {
                var stats = swfuploader.getStats();
                if (stats.files_queued == 0 &&
                    (existsUploadedFields(document.getElementById('wfl_form1'), required_xsd_display_fields_fileupload) == false) ){
                    document.getElementById('uploaderUploadButton').style.backgroundColor = '#FF9999';
                    errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
                }
            } else {
                // We only required min one field, so checking on the first field is sufficient
                var field = document.getElementsByName(required_fields[i].text)[0];
                if (isWhitespace(field.value)) {
                    errors[errors.length] = new Option(getFieldTitle(xsd_display_fields,required_fields[i].text), required_fields[i].text);
                }
            }
        }
    }
}

/**
 * Check the existence of an input field that stores a flag of file upload completion.
 * This input is instantiated on swfuploader.js file.
 *
 * @param f. Form object where we want to search the input field.
 * @param required_fields. An array containing the fieldname and the field's validation title.
 * @return Boolean. True when field exists and contain any value.
 */
function existsUploadedFields(f, required_fields)
{
    var output = false;
    for (var i = 0; i < required_fields.length; i++) {
        var field = getFormElement(f, required_fields[i].text);
        if (!isWhitespace(field.value)) {
            output = true;
        }
    }
    return output;
}


function checkUploadedFiles(f, required_fields)
{
    errors = new Array();
    for (var i = 0; i < required_fields.length; i++) {
        var field = getFormElement(f, required_fields[i].text);
        if (isWhitespace(field.value)) {
            errors[errors.length] = new Option(required_fields[i].value);
        }
    }

    if (errors.length > 0) {
        var fields = '';
        for (var i = 0; i < errors.length; i++) {
            fields += '- ' + errors[i].value + "\n";
        }

        // enable buttons that previously disabled by submit action
        enableWorkflowButtons(f);
        uploaderEnableWorkflowButtons();
        enableAddMoreButton();

        // show alert box with the missing uploaded file(s)
        alert("The files failed to be uploaded for the following fields: \n\n" + fields + "\nPlease try again.");
        return false;
    } else {
        return true;
    }
}

function checkRequiredInstantCustomFields(f, required_fields)
{
    for (var i = 0; i < required_fields.length; i++) {
        var field = getFormElement(f, required_fields[i].text);
        if (required_fields[i].value == 'combo') {
            if ((getSelectedOption(f, field.name) == '-1') || (getSelectedOption(f, field.name) == '')) {
                errors[errors.length] = new Option(getFieldTitle(instant_custom_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'multiple') {
            if (!hasOneSelectedValue(f, field.name)) {
                errors[errors.length] = new Option(getFieldTitle(instant_custom_fields,required_fields[i].text), required_fields[i].text);
            }
        } else if (required_fields[i].value == 'whitespace') {
            if (isWhitespace(field.value)) {
                errors[errors.length] = new Option(getFieldTitle(instant_custom_fields,required_fields[i].text), required_fields[i].text);
            }
        }
    }
}

function checkErrorCondition(form_name, field_name)
{
    var f = getForm(form_name);
    var field = getFormElement(f, field_name);
    if ((field.type == 'text') || (field.type == 'textarea') || (field.type == 'password')) {
        if (!isWhitespace(field.value)) {
            errorDetails(f, field_name, false);
        }
    } else if (field.type == 'select-one') {
        if (getSelectedOption(f, field_name) != '-1') {
            errorDetails(f, field_name, false);
        }
    } else if (field.type == 'select-multiple') {
        if (hasOneSelectedValue(f, field_name)) {
            errorDetails(f, field_name, false);
        }
    }
}

function selectField(f, field_name)
{
    if (field_name == 'uploader_files_uploaded'){
        errorDetails(f, field_name, true);
    }

    for (var i = 0; i < f.elements.length; i++) {
		if (f.elements[i].name == field_name) {
			if ((f.elements[i].type != 'hidden') && (field_name.indexOf("[]") == -1))  {
				f.elements[i].focus();
            }
            errorDetails(f, field_name, true);
            if (isWhitespace(f.name)) {
                return false;
            }
			var newF = new Function('checkErrorCondition(\'' + f.name + '\', \'' + field_name + '\');');
			if (f.elements[i].onchange) {
                            // don't muck around with existing onchange stuff because it
                            // blows away the arguments to the oldF and thigns stop working
				//var oldF = (f.elements[i].onchange);
				//f.elements[i].onchange = function () { oldF(); newF();};
			} else {
				f.elements[i].onchange = function () {newF();};
			}
			if (f.elements[i].select) {
                f.elements[i].select();
            }
        }
    }
}


function getSelectedOption(f, field_name)
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            return f.elements[i].options[f.elements[i].selectedIndex].value;
        }
    }
}

function getSelectedOptionObject(f, field_name)
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            return f.elements[i].options[f.elements[i].selectedIndex];
        }
    }
}

var errors = null;

function checkFormSubmission(f, callback_func)
{
    errors = new Array();
    eval(callback_func + '(f);');
    if (errors.length > 0) {
        // loop through all of the broken fields and select them
        var fields = '';
        for (var i = 0; i < errors.length; i++) {
            selectField(f, errors[i].value);
            fields += '- ' + errors[i].text + "\n";
        }
        // show a big alert box with the missing information
        alert("The following fields need to be filled out or corrected:\n\n" + fields + "\nPlease complete these fields.");
        return false;
    } else {
        return true;
    }
}

function isValidSolrFilename(s)
{
	// check string length
	if (s.length > 45) {
		return false;
	}

	// check if it starts with a digit
	// check for upper/lower alphanumeric characters with underscores
	// check for only one file extension (only one period character)
	// check that the file extension is only numbers and lowercase letters
	var regexp = /^[a-zA-Z][a-zA-Z0-9_]*[\.][a-z0-9]+$/;

	return regexp.test(s);
}

function CheckFileClassifications()
{
    //Create warning for user if any files are added
    fileCheck = '';
    fileTable = document.getElementById('uploader_file_table');
    if (fileTable != null) {
        numberFiles = fileTable.rows.length - 1;
        for (index = 0; index < numberFiles/2; ++index) {
            $uploadFilename = document.getElementById('uploader_file_table').rows[index*2+1].cells[0].innerHTML;
            if ($uploadFilename != '') {
                var e = document.getElementById('filePermissionsNew[' + index + ']');

                //Thesis form has no permissions
                if(e != null) {
                     fileUploadType = e.options[e.selectedIndex].value;
                    if (fileUploadType == 0) {
                        window.alert('You must tell us the file classification of all attached files');
                        return false;
                    }
                    //No need to check if HERDC since it's private
                    if (fileUploadType != 5) {
                        $uploadFilename = $uploadFilename.replace(/^.*[\\\/]/, '');
                        $embargoDate = document.getElementsByName('embargo_date[' + index + ']')[0].value;
                        $embargoText = ($embargoDate) ? $embargoDate : 'Immediate';
                        fileCheck = fileCheck + '<br/>File: ' + $uploadFilename + '    Open Access Release Date: ' + $embargoText + '\n';
                    }
                }
            }
        }
        if (fileCheck != '') {
            var fileCheck = 'The following files will be submitted as open access and will be made publicly available immediately or on the date nominated. Please click Cancel if you do not wish to proceed.<br />' + fileCheck + '<br /><br />All other files submitted will be accessible by UQ eSpace administrators and Unit Publications Officers only.';
            $('<div>' + fileCheck + '</div>').dialog({
                resizable: false,
                width: "450px",
                title: "Open Access Warning:",
                modal: true,
                buttons: {
                    "OK": function() {
                        disableWorkflowButtons(this);
                        swfuploaderUploadFiles(this);
                        $(this).dialog("close"); //close confirmation
                    },
                    Cancel: function() {
                        $(this).dialog("close"); //close confirmation
                    }
                }
            });
            return false;
        } else {
            return true;
        }
    }
}
//-->
