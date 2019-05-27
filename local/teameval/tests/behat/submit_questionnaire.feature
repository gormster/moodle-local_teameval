@local @local_teameval

Feature: In a team evaluation, students submit responses to a questionnaire
    In order to evaluate my teammates
    As a student
    I need to submit responses to a team evaluation questionnaire

    Background:
        Given the following "courses" exist:
            | fullname | shortname | category | groupmode |
            | Course 1 | C1 | 0 | 1 |

        And the following team evaluation exists:
            | Course | Course 1 |
            | Course shortname | C1 |
            | Groups | 3 |
            | Students per group | 4 |
            | Assignment name | Test assignment name |

    @javascript
    Scenario: Team evaluation always visible to teacher
        Given I log in as "teacher1"
        And I am on "Course 1" course homepage

        When I follow "Test assignment name"
        And I wait until the page is ready

        Then I should see "Team evaluation"
        And I should see "Add Question"

    @javascript
    Scenario: Team evaluation not yet available
        Given I log in as "student1"
        And I am on "Course 1" course homepage

        When I follow "Test assignment name"

        Then I should not see "Team evaluation"

    @javascript
    Scenario: Team evaluation becomes available to students after submitting
        Given I log in as "teacher1"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"
        And I wait until the page is ready
        And I add a "likert" question to team evaluation with:
            | Title | Test Likert Question              |
            | Description | This is testing the likert question type |
            | From | 0 |
            | To | 3 |
            | meanings[0] | Zero |
            | meanings[1] | One |
            | meanings[2] | Two |
            | meanings[3] | Three |
        And I add a "split100" question to team evaluation with:
            | Title | Test Split 100 Question              |
            | Description | This is testing the split-100 question type |
        And I add a "comment" question to team evaluation with:
            | Title | Test Comment Question              |
            | Description | This is testing the comment question type |

        And I log out
        And I log in as "student1"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"

        Then I should not see "Team evaluation"

        When I press "Add submission"
        And I wait until the page is ready
        And I set the following fields to these values:
          | Online text | This is some online text for group A |
        And I press "Save changes"

        Then I should see "Team evaluation"

        And I press "Randomise"
        And I press "Submit"
        And I wait until "Saved!" "text" exists
        And I log out

        And I log in as "teacher1"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"
        And I navigate to "View all submissions" in current page administration
        And I click on "Grade" "link" in the "Student1 Example" "table_row"
        And I set the field "Grade out of 100" to "50"
        And I set the field "Notify students" to "0"
        And I press "Save changes"
        And I press "Ok"

        And I am on "Course 1" course homepage
        And I navigate to "View > Single view" in the course gradebook

        When I select "Test assignment name" from the "itemid" singleselect
        Then the field "Grade for Student1 Example" matches value ""
        And the field "Grade for Student2 Example" matches value ""
        And the field "Grade for Student3 Example" matches value ""
        And the field "Grade for Student4 Example" matches value ""

        When I log out

        And I log in as "student2"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"
        And I press "Randomise"
        And I press "Submit"
        And I wait until "Saved!" "text" exists
        And I log out

        And I log in as "student3"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"
        And I press "Randomise"
        And I press "Submit"
        And I wait until "Saved!" "text" exists
        And I log out

        And I log in as "student4"
        And I am on "Course 1" course homepage
        And I follow "Test assignment name"
        And I press "Randomise"
        And I press "Submit"
        And I wait until "Saved!" "text" exists
        And I log out

        And I log in as "teacher1"
        And I am on "Course 1" course homepage
        And I navigate to "View > Single view" in the course gradebook

        When I select "Test assignment name" from the "itemid" singleselect

        Then the field "Grade for Student1 Example" does not match value ""
        And the field "Grade for Student2 Example" does not match value ""
        And the field "Grade for Student3 Example" does not match value ""
        And the field "Grade for Student4 Example" does not match value ""
