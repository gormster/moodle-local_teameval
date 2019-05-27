from pprint import pformat

# copy data out from numbers
# follow the template in simpletest.numbers
# each row is a marked user, and each column is the response from one of their teammates
# for one question per column.

# use the empty string to represent null

allmarks = [

"""5	3	23	0	2	28	2	3	100	0	1	100
0	1	67	0	3	34	1	1	36	0	1	100
3	3	35	0	3	15	2	1	31	0	1	100
0	3	90	0	2	64	5	3	56	0	1	100""",

"""1	2	10	4	1		1	1	17	0	3	70
4	3	10	0	1		4	3	33	3	2	93
2	1	10	3	1		0	1	97	4	3	82
3	2	10	4	3		5	3	44	2	1	43""",

]

groups = []
for rawmarks in allmarks:
    grades = [line.split('\t') for line in rawmarks.splitlines()]

    numu = len(grades) # number of users
    numq = len(grades[0]) / numu # number of questions

    markers = [[] for _ in range(numu)] # make numu empty lists

    qwise = zip(*grades) # get list of tuples reading down each column
    chunks = zip(*[iter(qwise)]*numq) # one chunk for each user's marks
    for (c, m) in zip(chunks, markers):
        for q in c:
            m.append([None if i == '' else int(i) for i in q])

    groups.append(markers)

pretty = pformat(groups, width=80)
pretty = pretty.replace('None', 'null')

print pretty
