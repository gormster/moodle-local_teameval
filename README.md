# Team Evaluation

Team Evaluation is a local plugin designed to be adopted by grading plugins which offer group submission; in other words, plugins where a group will receive a single grade for a single piece of work.

Team Evaluation takes information from the students in a group, uses that information to determine which members contributed the most to the project, and then adjusts the invidual grades based on that assessment.

Team Evaluation is in public alpha; we have just three question plugins, one evaluator plugin and a handful of reports. Contributions are always welcome!

Moodle 3.5 and 3.7 are supported. (3.6 presumably is also? But untested.)

# Adding Team Evaluation to your Moodle

Team Evaluation is not currently on the Moodle plugins directory. This is partially because it requires grading plugins to adopt it. At present only the Assignment plugin supports group submission (we have a customised version of Workshop that also supports group submission, among other things, available at http://github.com/gormster/moodle-mod_workshep). To add support to Assignment, apply the patch file `mod_assign.patch` using `git apply` or similar. (If you can't apply this patch, then there's probably some very significant differences; please let us know!).

# Plugin developers

Please read the [implementer's guide](/IMPLEMENTERS.md).