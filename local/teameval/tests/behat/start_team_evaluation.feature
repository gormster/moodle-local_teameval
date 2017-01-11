@local @local_teameval

Feature: In a course, a teacher creates a module and starts team evaluation
    In order to start team evaluation
    As a teacher
    I need to enable team evaluation in an activity module

    Background:
        Given the following "courses" exist:
            | fullname  | shortname | category  | groupmode |
            | Course 1  | C1        | 0         | 1         |

        And the following "users" exist:
            | username  | firstname | lastname  | email                 |
            | teacher1  | Teacher   | 1         | teacher1@example.com  |
            | student1  | Student   | 1         | student1@example.com  |
            | student2  | Student   | 2         | student2@example.com  |
            | student3  | Student   | 3         | student3@example.com  |
            | student4  | Student   | 4         | student4@example.com  |
            | student5  | Student   | 5         | student5@example.com  |
            | student6  | Student   | 6         | student6@example.com  |

        And the following "course enrolments" exist:
            | course    | user      | role              |
            | C1        | teacher1  | editingteacher    |
            | C1        | student1  | student           |
            | C1        | student2  | student           |
            | C1        | student3  | student           |
            | C1        | student4  | student           |
            | C1        | student5  | student           |
            | C1        | student6  | student           |

    @javascript
    Scenario: Team submission assignment allows local_teameval
        Given I log in as "teacher1"
        And I follow "Course 1"
        And I turn editing mode on
        And I add a "Assignment" to section "1" and I fill the form with:
            | name | Test teameval assignment |
            | Description | This assignment should allow team evaluation |
            | assignsubmission_onlinetext_enabled | 1 |
            | assignsubmission_file_enabled | 0 |
            | teamsubmission | Yes |
            | groupmode | Visible groups |

        When I follow "Test teameval assignment"
        And I wait until the page is ready

        Then I should see "Team evaluation" in the ".local-teameval-container-heading" "css_element"

        When I click on "Turn On Team Evaluation" "button"
        And I wait until the page is ready

        Then I should see "Team evaluation" in the ".local-teameval-container-heading" "css_element"
        And I should see "Settings" in the ".local-teameval-containerbox" "css_element"
        And I should see "Add Question" in the ".local-teameval-containerbox" "css_element"

    @javascript
    Scenario: Individual submission assignment does not allow local_teameval
        Given I log in as "teacher1"
        And I follow "Course 1"
        And I turn editing mode on
        And I add a "Assignment" to section "1" and I fill the form with:
            | name | Test teameval assignment |
            | Description | This assignment should not allow team evaluation |
            | assignsubmission_onlinetext_enabled | 1 |
            | assignsubmission_file_enabled | 0 |
            | teamsubmission | No |
            | groupmode | Visible groups |

        When I follow "Test teameval assignment"
        And I wait until the page is ready

        Then ".local-teameval-container-heading" "css_element" should not exist

    # TODO: Add workshep checks if installed. Not quite sure what the step is to skip if an activity doesn't exist.
