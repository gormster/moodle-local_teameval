define(['jquery'], function($) {

    // Matches the field name + any square brackets
    var a = /([^\[\]]+)((?:\[[^\[\]]*\])+)?/;
    // Matches the values in square brackets
    var b = /\[([^\[\]]*)\]/g;

    function getPath(str) {
        var path = [];
        var m = a.exec(str);
        if (m) {
            path.push(m[1]);
            if (m[2]) {
                var x;
                while((x = b.exec(m[2]))) {
                    path.push(x[1]);
                }
            }
        }
        return path;
    }

    function setValue(obj, path, value) {
        var key = path.shift();
        if (path.length > 0) {
            if (key === '' || !obj[key]) {
                var nextKey = path[0];
                var newVal = {};
                if ((nextKey === '') || (parseInt(nextKey) == nextKey)) { //nextKey is an integer
                    newVal = [];
                }
                setValue(newVal, path, value);
                if (key === '') {
                    obj.push(newVal);
                } else {
                    obj[key] = newVal;
                }
            } else {
                setValue(obj[key], path, value);
            }
        } else {
            if (key === '') {
                obj.push(value);
            } else {
                obj[key] = value;
            }
        }
    }

    function fixCheckboxes(form, array) {
        var unchecked = $(form).find('input[type=checkbox]:not(:checked)').map(function() { return this.name; }).get();
        var output = [];
        for (var i = 0; i < array.length; i++) {
            var field = array[i];
            if (unchecked.indexOf(field.name) !== -1) {
                continue;
            } else {
                output.push(field);
            }
        }
        return output;
    }

    function serializeObject(form, options) {
        options = Object.assign({
            fixCheckboxes: false
        }, options);

        var array = $(form).serializeArray();

        if (options.fixCheckboxes) {
            array = fixCheckboxes(form, array);
        }

        var object = {};
        for (var i = 0; i < array.length; i++) {
            var field = array[i];
            setValue(object, getPath(field.name), field.value);
        }
        return object;
    }

    return {
        'getPath': getPath,
        'setValue': setValue,
        'serializeObject': serializeObject
    };

});
