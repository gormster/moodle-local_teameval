define([], function() {
    var hiragana = [
        "a", "i", "u", "e", "o",
        "ka", "ki", "ku", "ke", "ko",
        "ga", "gi", "gu", "ge", "go",
        "sa", "shi", "su", "se", "so",
        "za", "ji", "zu", "ze", "zo",
        "ta", "chi", "tsu", "te", "to",
        "da", "de", "do",
        "na", "ni", "nu", "ne", "no",
        "ha", "hi", "fu", "he", "ho",
        "ba", "bi", "bu", "be", "bo",
        "pa", "pi", "pu", "pe", "po",
        "ma", "mi", "mu", "me", "mo",
        "ya", "yu", "yo",
        "ra", "ri", "ru", "re", "ro",
        "wa", "wi", "wo"
    ];

    function generateWord() {

        var len = Math.round(Math.random() * 4) + 2;

        var output = "";
        for(var i = 0; i < len; i++) {
            var el = Math.floor(Math.random() * hiragana.length);
            output += hiragana[el];
        }

        return output;

    }

    function capitalise(s) {

        return s.charAt(0).toUpperCase() + s.substring(1);

    }

    function generateSentence() {

        var len = Math.round(Math.random() * 10) + 4;

        var output = [];
        for(var i = 0; i < len; i++) {
            var word = generateWord();
            if (Math.random() * 10 < 1) {
                word = capitalise(word);
            }
            output.push(word);
        }

        var sentence = output.join(" ");

        return capitalise(sentence) + ".";

    }

    return {
        generateWord: generateWord,
        generateSentence: generateSentence
    };

});

