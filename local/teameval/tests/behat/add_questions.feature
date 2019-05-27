@local @local_teameval

Feature: Teachers add, remove and reorder questions in a team evaluation questionnaire
    In order for my students to evaluate each other
    As a teacher
    I need to be able to craft a relevant questionnaire

    Background:
        Given the following "courses" exist:
            | fullname | shortname | category | groupmode |
            | Course 1 | C1 | 0 | 1 |

        And the following team evaluation exists:
            | Course | Course 1 |
            | Course shortname | C1 |
            | Groups | 3 |
            | Students per group | 4 |
            | Assignment name | Test assignment |

    @javascript
    Scenario:
        Given I log in as "teacher1"
        And I am on "Course 1" course homepage
        And I follow "Test assignment"
        And I click on "Add Question" "link" in the ".local-teameval-containerbox" "css_element"
        And I click on "Likert" "list_item"
        And I set the following fields to these values:
            | Title | Test Likert Question              |
            | Description | This is testing the likert question type |
            | From | 0 |
            | To | 3 |
        And I set the following fields to these values:
            | meanings[0] | Zero |
            | meanings[1] | One |
            | meanings[2] | Two |
            | meanings[3] | Three |

        When I click on "Save" "button" in the ".local-teameval-question.editing" "css_element"
        And I wait until ".teamevalquestion-likert-question-submission" "css_element" exists

        Then I should see "Test Likert Question" in the ".teamevalquestion-likert-question-submission" "css_element"
        And I should see "This is testing the likert question type" in the ".teamevalquestion-likert-question-submission" "css_element"
        And I should see "0: Zero" in the "responses" "table"
        And I should see "1: One" in the "responses" "table"
        And I should see "2: Two" in the "responses" "table"
        And I should see "3: Three" in the "responses" "table"
        And I should see "Yourself" in the "responses" "table"
        And I should see "Example user" in the "responses" "table"
