#include <stdio.h>

const char service_name = 'process-demo';
const int process_min_num = 1;
const int process_max_num = 2;
const char pid_path = '/';
const char pid_file = 'process.pid';

int main()
{
    int pid = fork();

    printf("%d \n", pid);

    sleep(1000 * 10);

    return 0;
}
