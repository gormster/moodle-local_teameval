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
        And I follow "Course 1"

        When I follow "Test assignment name"
        And I wait until the page is ready

        Then I should see "Team evaluation"
        And I should see "Add Question"

    @javascript
    Scenario: Team evaluation not yet available
        Given I log in as "student1"
        And I follow "Course 1"

        When I follow "Test assignment name"

        Then I should not see "Team evaluation"
